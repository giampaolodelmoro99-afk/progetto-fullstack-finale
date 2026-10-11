import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { AssistantSuggestions } from '@/features/assistant/components/AssistantSuggestions';

export function AssistantWelcome() {
  return (
    <Stack sx={{ gap: 2 }}>
      <Stack sx={{ gap: 0.5 }}>
        <Typography variant="h3" component="p">
          Ciao, come posso aiutarti?
        </Typography>
        <Typography variant="body2" sx={{ color: 'text.secondary' }}>
          Chiedimi dei prodotti, della spedizione o dei resi.
        </Typography>
      </Stack>
      <AssistantSuggestions />
    </Stack>
  );
}
