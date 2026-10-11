import SearchOutlined from '@mui/icons-material/SearchOutlined';
import LinearProgress from '@mui/material/LinearProgress';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';

export function AssistantWaiting() {
  return (
    <Paper variant="outlined" sx={{ p: 2, display: 'flex', flexDirection: 'column', gap: 1.5 }}>
      <Stack direction="row" sx={{ alignItems: 'center', gap: 1 }}>
        <SearchOutlined fontSize="small" sx={{ color: 'text.secondary' }} />
        <Typography variant="body2">Sto preparando la risposta…</Typography>
      </Stack>
      <LinearProgress aria-label="Sto preparando la risposta" />
      <Typography variant="caption" sx={{ color: 'text.secondary' }}>
        Di solito servono 15–20 secondi.
      </Typography>
    </Paper>
  );
}
