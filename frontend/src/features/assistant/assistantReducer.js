export const initialAssistantState = {
  messages: [],
  status: 'idle',
  error: null,
  requestId: null,
};

function dropTrailingFailedAnswers(messages) {
  const kept = [...messages];

  while (kept.length > 0 && kept[kept.length - 1].failed) {
    kept.pop();
  }

  return kept;
}

function updateAnswer(messages, requestId, update) {
  return messages.map((message) => (message.id === requestId ? update(message) : message));
}

function hasAnswer(messages, requestId) {
  return messages.some((message) => message.id === requestId);
}

function newAnswer(requestId, overrides = {}) {
  return {
    id: requestId,
    role: 'assistant',
    content: '',
    products: [],
    interrupted: false,
    failed: false,
    ...overrides,
  };
}

function finish(state, messages, overrides = {}) {
  return { ...state, messages, status: 'idle', requestId: null, ...overrides };
}

export function assistantReducer(state, action) {
  if (action.type === 'start') {
    const messages = dropTrailingFailedAnswers(state.messages);

    return {
      ...state,
      messages: action.question ? [...messages, action.question] : messages,
      status: 'waiting',
      error: null,
      requestId: action.requestId,
    };
  }

  if (action.type === 'reset') {
    return initialAssistantState;
  }

  if (action.requestId !== state.requestId || state.requestId === null) {
    return state;
  }

  const { requestId } = action;

  switch (action.type) {
    case 'text':
      return {
        ...state,
        status: 'streaming',
        messages: hasAnswer(state.messages, requestId)
          ? updateAnswer(state.messages, requestId, (message) => ({
              ...message,
              content: message.content + action.delta,
            }))
          : [...state.messages, newAnswer(requestId, { content: action.delta })],
      };
    case 'products':
      return {
        ...state,
        messages: hasAnswer(state.messages, requestId)
          ? updateAnswer(state.messages, requestId, (message) => ({
              ...message,
              products: action.products,
            }))
          : [...state.messages, newAnswer(requestId, { products: action.products })],
      };
    case 'done':
      return finish(state, state.messages);
    case 'stop':
      return finish(
        state,
        hasAnswer(state.messages, requestId)
          ? updateAnswer(state.messages, requestId, (message) => ({
              ...message,
              products: [],
              interrupted: true,
            }))
          : [...state.messages, newAnswer(requestId, { interrupted: true })],
      );
    case 'fail':
      return finish(
        state,
        updateAnswer(state.messages, requestId, (message) => ({ ...message, failed: true })),
        { status: 'error', error: action.error },
      );
    default:
      return state;
  }
}
