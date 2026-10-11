import Box from '@mui/material/Box';
import { STOPPED_MESSAGE } from '@/features/assistant/assistantConstants';
import { useAssistant } from '@/features/assistant/hooks/useAssistant';
import { toPlainText } from '@/features/assistant/utils/parseLightMarkdown';
import { stripProductMarkers } from '@/features/assistant/utils/stripProductMarkers';

function announcementFor(messages, status, error) {
  if (status === 'error') {
    return error?.message ?? '';
  }

  const last = messages[messages.length - 1];

  if (status !== 'idle' || last?.role !== 'assistant') {
    return '';
  }

  return last.interrupted ? STOPPED_MESSAGE : toPlainText(stripProductMarkers(last.content));
}

export function AssistantLiveRegion() {
  const { messages, status, error } = useAssistant();

  return (
    <Box role="status" aria-live="polite" sx={(theme) => theme.mixins.visuallyHidden}>
      {announcementFor(messages, status, error)}
    </Box>
  );
}
