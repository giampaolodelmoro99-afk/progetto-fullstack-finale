import Dialog from '@mui/material/Dialog';
import Paper from '@mui/material/Paper';
import { AssistantPanelContent } from '@/features/assistant/components/AssistantPanelContent';
import { useAssistant } from '@/features/assistant/hooks/useAssistant';

export const ASSISTANT_TITLE_ID = 'assistant-panel-title';

export function AssistantPanel() {
  const { open, isDesktop, closePanel } = useAssistant();

  if (!isDesktop) {
    return (
      <Dialog fullScreen open={open} onClose={closePanel} aria-labelledby={ASSISTANT_TITLE_ID}>
        <AssistantPanelContent titleId={ASSISTANT_TITLE_ID} />
      </Dialog>
    );
  }

  if (!open) {
    return null;
  }

  function handleKeyDown(event) {
    if (event.key === 'Escape') {
      event.stopPropagation();
      closePanel();
    }
  }

  return (
    <Paper
      role="dialog"
      aria-labelledby={ASSISTANT_TITLE_ID}
      elevation={8}
      onKeyDown={handleKeyDown}
      sx={(theme) => ({
        position: 'fixed',
        right: theme.spacing(3),
        bottom: theme.spacing(10),
        zIndex: 'fab',
        width: theme.layout.assistantPanelWidth,
        maxWidth: `calc(100vw - ${theme.spacing(6)})`,
        height: theme.layout.assistantPanelMaxHeight,
        display: 'flex',
        flexDirection: 'column',
        overflow: 'hidden',
        border: 1,
        borderColor: 'divider',
        borderRadius: 2,
      })}
    >
      <AssistantPanelContent titleId={ASSISTANT_TITLE_ID} />
    </Paper>
  );
}
