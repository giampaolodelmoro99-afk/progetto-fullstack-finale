import ArrowForward from '@mui/icons-material/ArrowForward';
import Button from '@mui/material/Button';
import Stack from '@mui/material/Stack';
import { SUGGESTIONS } from '@/features/assistant/assistantConstants';
import { useAssistant } from '@/features/assistant/hooks/useAssistant';

export function AssistantSuggestions() {
  const { send, isBlocking } = useAssistant();

  return (
    <Stack
      component="ul"
      aria-label="Domande suggerite"
      sx={{ gap: 1, m: 0, p: 0, listStyle: 'none' }}
    >
      {SUGGESTIONS.map((suggestion) => (
        <li key={suggestion}>
          <Button
            variant="outlined"
            fullWidth
            endIcon={<ArrowForward />}
            disabled={isBlocking}
            onClick={() => send(suggestion)}
            sx={{ justifyContent: 'space-between', textAlign: 'left', bgcolor: 'background.paper' }}
          >
            {suggestion}
          </Button>
        </li>
      ))}
    </Stack>
  );
}
