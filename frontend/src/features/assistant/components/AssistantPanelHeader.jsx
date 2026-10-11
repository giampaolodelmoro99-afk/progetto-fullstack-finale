import Add from '@mui/icons-material/Add';
import Close from '@mui/icons-material/Close';
import Button from '@mui/material/Button';
import IconButton from '@mui/material/IconButton';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useAssistant } from '@/features/assistant/hooks/useAssistant';

export function AssistantPanelHeader({ titleId }) {
  const { messages, closePanel, reset, registerTitle } = useAssistant();

  return (
    <Stack sx={{ px: 2, pt: 2, pb: 1, gap: 0.5 }}>
      <Stack direction="row" sx={{ alignItems: 'center', justifyContent: 'space-between', gap: 1 }}>
        <Typography
          ref={registerTitle}
          variant="h6"
          component="h3"
          id={titleId}
          tabIndex={-1}
          sx={{ whiteSpace: 'nowrap' }}
        >
          Assistente di Casa del Silicio
        </Typography>
        <IconButton aria-label="Chiudi l'assistente" onClick={closePanel}>
          <Close />
        </IconButton>
      </Stack>
      <Button
        variant="text"
        size="small"
        startIcon={<Add />}
        disabled={messages.length === 0}
        onClick={reset}
        sx={{ alignSelf: 'flex-start' }}
      >
        Nuova conversazione
      </Button>
    </Stack>
  );
}
