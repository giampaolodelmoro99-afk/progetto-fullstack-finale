import Container from '@mui/material/Container';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { StatusCard } from '@/features/system/components/StatusCard';
import { useCsrfCookie } from '@/features/system/hooks/useCsrfCookie';
import { useHealth } from '@/features/system/hooks/useHealth';

export default function SystemStatusPage() {
  const csrfQuery = useCsrfCookie();
  const healthQuery = useHealth({ enabled: csrfQuery.isSuccess });

  return (
    <Container maxWidth="sm" sx={{ py: 6 }}>
      <Stack sx={{ gap: 3 }}>
        <Stack sx={{ gap: 1 }}>
          <Typography variant="h4" component="h1">
            Verifica del collegamento
          </Typography>
          <Typography variant="body1" sx={{ color: 'text.secondary' }}>
            Pagina temporanea che controlla CORS e cookie tra il frontend e l&apos;API.
          </Typography>
        </Stack>
        <StatusCard
          title="Cookie CSRF"
          query={csrfQuery}
          getSuccessText={() => 'Cookie CSRF ricevuto: CORS con credenziali funzionante.'}
          waitingText="In attesa della richiesta del cookie CSRF."
        />
        <StatusCard
          title="Stato dell'API"
          query={healthQuery}
          getSuccessText={(data) => `Stato del servizio: ${data.status}`}
          waitingText={
            csrfQuery.isError
              ? 'Verifica non eseguita: serve prima il cookie CSRF.'
              : 'In attesa del cookie CSRF.'
          }
        />
      </Stack>
    </Container>
  );
}
