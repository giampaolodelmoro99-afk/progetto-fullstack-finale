import Box from '@mui/material/Box';
import Stack from '@mui/material/Stack';
import { useEffect, useRef } from 'react';
import { REFUSAL_MESSAGE } from '@/features/assistant/assistantConstants';
import { AssistantErrorAlert } from '@/features/assistant/components/AssistantErrorAlert';
import { AssistantMessage } from '@/features/assistant/components/AssistantMessage';
import { AssistantStopButton } from '@/features/assistant/components/AssistantStopButton';
import { AssistantSuggestions } from '@/features/assistant/components/AssistantSuggestions';
import { AssistantWaiting } from '@/features/assistant/components/AssistantWaiting';
import { AssistantWelcome } from '@/features/assistant/components/AssistantWelcome';
import { useAssistant } from '@/features/assistant/hooks/useAssistant';
import { stripProductMarkers } from '@/features/assistant/utils/stripProductMarkers';

const BOTTOM_THRESHOLD = 24;

function endsWithRefusal(messages) {
  const last = messages[messages.length - 1];

  return (
    last?.role === 'assistant' &&
    !last.interrupted &&
    !last.failed &&
    stripProductMarkers(last.content).trimStart().startsWith(REFUSAL_MESSAGE)
  );
}

export function AssistantConversation() {
  const { messages, status, requestId } = useAssistant();
  const scrollArea = useRef(null);
  const pinnedToBottom = useRef(true);
  const busy = status === 'waiting' || status === 'streaming';

  useEffect(() => {
    const element = scrollArea.current;

    if (element && (pinnedToBottom.current || status === 'waiting')) {
      element.scrollTop = element.scrollHeight;
      pinnedToBottom.current = true;
    }
  }, [messages, status]);

  function handleScroll(event) {
    const { scrollHeight, scrollTop, clientHeight } = event.currentTarget;

    pinnedToBottom.current = scrollHeight - scrollTop - clientHeight <= BOTTOM_THRESHOLD;
  }

  return (
    <Box
      ref={scrollArea}
      onScroll={handleScroll}
      role="group"
      aria-label="Conversazione"
      aria-busy={busy}
      sx={{ flexGrow: 1, minHeight: 0, overflowY: 'auto', bgcolor: 'background.default', p: 2 }}
    >
      {messages.length === 0 && status === 'idle' ? (
        <AssistantWelcome />
      ) : (
        <Stack sx={{ gap: 2 }}>
          {messages.map((message) => (
            <AssistantMessage
              key={message.id}
              message={message}
              streaming={status === 'streaming' && message.id === requestId}
            />
          ))}
          {status === 'waiting' ? <AssistantWaiting /> : null}
          {busy ? <AssistantStopButton /> : null}
          {status === 'idle' && endsWithRefusal(messages) ? <AssistantSuggestions /> : null}
          {status === 'error' ? <AssistantErrorAlert /> : null}
        </Stack>
      )}
    </Box>
  );
}
