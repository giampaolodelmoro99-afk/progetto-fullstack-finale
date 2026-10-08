import Alert from '@mui/material/Alert';
import AlertTitle from '@mui/material/AlertTitle';
import Button from '@mui/material/Button';

export function ErrorState({ title, message, onRetry }) {
  return (
    <Alert
      severity="error"
      action={
        onRetry ? (
          <Button color="inherit" size="small" onClick={onRetry}>
            Riprova
          </Button>
        ) : null
      }
    >
      {title ? <AlertTitle>{title}</AlertTitle> : null}
      {message}
    </Alert>
  );
}
