import Alert from '@mui/material/Alert';
import AlertTitle from '@mui/material/AlertTitle';
import Box from '@mui/material/Box';
import { VisuallyHidden } from '@/components/feedback/VisuallyHidden';

function retryMessage(seconds) {
  return `Troppi tentativi. Riprova tra ${seconds} ${seconds === 1 ? 'secondo' : 'secondi'}`;
}

export function TooManyAttemptsAlert({ rateLimit, children }) {
  if (!rateLimit.isLimited) {
    return null;
  }

  return (
    <Alert severity="warning">
      <AlertTitle>
        {rateLimit.retryAfter === null ? (
          'Troppi tentativi. Riprova tra poco'
        ) : (
          <>
            <VisuallyHidden>{retryMessage(rateLimit.retryAfter)}</VisuallyHidden>
            <Box component="span" aria-hidden="true">
              {retryMessage(rateLimit.secondsLeft)}
            </Box>
          </>
        )}
      </AlertTitle>
      {children}
    </Alert>
  );
}
