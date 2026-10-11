import { useCallback, useEffect, useMemo, useReducer, useRef, useState } from 'react';
import { INTERRUPTED_ANSWER_MESSAGE } from '@/features/assistant/assistantConstants';
import { streamError, toAssistantError } from '@/features/assistant/assistantErrors';
import { assistantReducer, initialAssistantState } from '@/features/assistant/assistantReducer';
import { streamAssistantAnswer } from '@/features/assistant/api/assistantApi';
import { AssistantContext } from '@/features/assistant/hooks/useAssistant';
import { buildHistory } from '@/features/assistant/utils/buildHistory';
import { useAuth } from '@/features/auth/hooks/useAuth';
import { useIsDesktop } from '@/hooks/useIsDesktop';
import { useRateLimit } from '@/hooks/useRateLimit';

function focusInsidePanel(element) {
  if (!element) {
    return;
  }

  const panel = element.closest('[role="dialog"]');
  const active = document.activeElement;

  if (active === null || active === document.body || panel?.contains(active)) {
    element.focus();
  }
}

function firstEnabled(...candidates) {
  return candidates.find((element) => element && !element.disabled) ?? null;
}

function useFocusFollowsStatus({ open, status, isBlocking, resetCount, elements }) {
  const previous = useRef({ open, status, isBlocking, resetCount });

  useEffect(() => {
    const before = previous.current;
    previous.current = { open, status, isBlocking, resetCount };
    const { input, stop, errorAction, alert, title } = elements.current;

    if (!open || !before.open) {
      return;
    }

    if (before.isBlocking && !isBlocking) {
      focusInsidePanel(input);

      return;
    }

    if (before.status === status && before.resetCount === resetCount) {
      return;
    }

    if (status === 'waiting') {
      focusInsidePanel(stop);
    } else if (status === 'idle') {
      focusInsidePanel(firstEnabled(input, title));
    } else if (status === 'error') {
      focusInsidePanel(firstEnabled(errorAction, input, alert));
    }
  }, [open, status, isBlocking, resetCount, elements]);
}

export function AssistantProvider({ children }) {
  const { user } = useAuth();
  const isDesktop = useIsDesktop();
  const [state, dispatch] = useReducer(assistantReducer, initialAssistantState);
  const [open, setOpen] = useState(false);
  const {
    isBlocking,
    secondsLeft,
    trigger: triggerRateLimit,
    clear: clearRateLimit,
  } = useRateLimit();
  const activeRequest = useRef(null);
  const nextId = useRef(0);
  const elements = useRef({
    input: null,
    stop: null,
    errorAction: null,
    alert: null,
    title: null,
    launcher: null,
  });
  const [resetCount, setResetCount] = useState(0);
  const registerElement = useCallback(
    (name) => (element) => {
      elements.current[name] = element;
    },
    [],
  );
  const registerInput = useMemo(() => registerElement('input'), [registerElement]);
  const registerStop = useMemo(() => registerElement('stop'), [registerElement]);
  const registerErrorAction = useMemo(() => registerElement('errorAction'), [registerElement]);
  const registerAlert = useMemo(() => registerElement('alert'), [registerElement]);
  const registerTitle = useMemo(() => registerElement('title'), [registerElement]);
  const registerLauncher = useMemo(() => registerElement('launcher'), [registerElement]);
  const userKey = user?.id ?? null;
  const [trackedUser, setTrackedUser] = useState(userKey);

  if (trackedUser !== userKey) {
    setTrackedUser(userKey);
    dispatch({ type: 'reset' });
  }

  const cancelActiveRequest = useCallback(() => {
    const request = activeRequest.current;
    activeRequest.current = null;
    request?.controller.abort();

    return request;
  }, []);

  useEffect(() => cancelActiveRequest, [userKey, cancelActiveRequest]);

  useFocusFollowsStatus({ open, status: state.status, isBlocking, resetCount, elements });

  const createId = useCallback((prefix) => {
    nextId.current += 1;

    return `${prefix}-${nextId.current}`;
  }, []);

  const run = useCallback(
    async (history, question) => {
      cancelActiveRequest();
      const controller = new AbortController();
      const requestId = createId('answer');
      const request = { controller, requestId };
      activeRequest.current = request;
      dispatch({ type: 'start', requestId, question });

      const isCurrent = () => activeRequest.current === request && !controller.signal.aborted;
      let finished = false;

      try {
        for await (const event of streamAssistantAnswer({
          messages: history,
          signal: controller.signal,
        })) {
          if (!isCurrent()) {
            return;
          }

          if (event.type === 'text') {
            dispatch({ type: 'text', requestId, delta: event.delta ?? '' });
          } else if (event.type === 'products') {
            dispatch({ type: 'products', requestId, products: event.data ?? [] });
          } else if (event.type === 'done') {
            finished = true;
            dispatch({ type: 'done', requestId });
            break;
          } else if (event.type === 'error') {
            finished = true;
            dispatch({
              type: 'fail',
              requestId,
              error: streamError(event.message ?? INTERRUPTED_ANSWER_MESSAGE),
            });
            break;
          }
        }

        if (!finished && isCurrent()) {
          dispatch({ type: 'fail', requestId, error: streamError(INTERRUPTED_ANSWER_MESSAGE) });
        }
      } catch (error) {
        if (!isCurrent()) {
          return;
        }

        const assistantError = toAssistantError(error);

        if (assistantError.kind === 'rate_limited') {
          triggerRateLimit(error);
        }

        dispatch({ type: 'fail', requestId, error: assistantError });
      } finally {
        if (activeRequest.current === request) {
          activeRequest.current = null;
        }
      }
    },
    [cancelActiveRequest, createId, triggerRateLimit],
  );

  const send = useCallback(
    (text) => {
      const question = { id: createId('question'), role: 'user', content: text };

      clearRateLimit();
      run(buildHistory([...state.messages, question]), question);
    },
    [createId, clearRateLimit, run, state.messages],
  );

  const retry = useCallback(() => {
    clearRateLimit();
    run(buildHistory(state.messages), null);
  }, [clearRateLimit, run, state.messages]);

  const stop = useCallback(() => {
    const request = cancelActiveRequest();

    if (request) {
      dispatch({ type: 'stop', requestId: request.requestId });
    }
  }, [cancelActiveRequest]);

  const reset = useCallback(() => {
    cancelActiveRequest();
    dispatch({ type: 'reset' });
    setResetCount((count) => count + 1);
  }, [cancelActiveRequest]);

  const openPanel = useCallback(() => setOpen(true), []);

  const closePanel = useCallback(() => {
    stop();
    setOpen(false);

    if (isDesktop) {
      elements.current.launcher?.focus();
    }
  }, [stop, isDesktop]);

  const closeOnMobile = useCallback(() => {
    if (!isDesktop) {
      closePanel();
    }
  }, [isDesktop, closePanel]);

  const value = useMemo(
    () => ({
      ...state,
      open,
      isDesktop,
      isBlocking,
      secondsLeft,
      registerInput,
      registerStop,
      registerErrorAction,
      registerAlert,
      registerTitle,
      registerLauncher,
      send,
      retry,
      stop,
      reset,
      openPanel,
      closePanel,
      closeOnMobile,
    }),
    [
      state,
      open,
      isDesktop,
      isBlocking,
      secondsLeft,
      registerInput,
      registerStop,
      registerErrorAction,
      registerAlert,
      registerTitle,
      registerLauncher,
      send,
      retry,
      stop,
      reset,
      openPanel,
      closePanel,
      closeOnMobile,
    ],
  );

  return <AssistantContext.Provider value={value}>{children}</AssistantContext.Provider>;
}
