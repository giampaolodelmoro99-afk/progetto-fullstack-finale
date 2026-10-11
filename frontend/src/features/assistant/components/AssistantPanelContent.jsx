import Divider from '@mui/material/Divider';
import Stack from '@mui/material/Stack';
import { AssistantComposer } from '@/features/assistant/components/AssistantComposer';
import { AssistantConversation } from '@/features/assistant/components/AssistantConversation';
import { AssistantLiveRegion } from '@/features/assistant/components/AssistantLiveRegion';
import { AssistantPanelHeader } from '@/features/assistant/components/AssistantPanelHeader';

export function AssistantPanelContent({ titleId }) {
  return (
    <Stack sx={{ height: 1, minHeight: 0, bgcolor: 'background.paper' }}>
      <AssistantPanelHeader titleId={titleId} />
      <Divider />
      <AssistantConversation />
      <Divider />
      <AssistantComposer />
      <AssistantLiveRegion />
    </Stack>
  );
}
