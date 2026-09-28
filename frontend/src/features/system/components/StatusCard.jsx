import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { normalizeApiError } from '@/utils/normalizeApiError';

function renderStatus({ title, query, getSuccessText, waitingText }) {
  if (query.isPending && query.fetchStatus === 'idle') {
    return <Alert severity="info">{waitingText}</Alert>;
  }

  if (query.isPending) {
    return (
      <Skeleton
        variant="rounded"
        role="status"
        aria-label={`Verifica in corso: ${title}`}
        sx={{ height: (theme) => theme.spacing(6) }}
      />
    );
  }

  if (query.isError) {
    return (
      <Alert
        severity="error"
        action={
          <Button color="inherit" size="small" onClick={() => query.refetch()}>
            Riprova
          </Button>
        }
      >
        {normalizeApiError(query.error).message}
      </Alert>
    );
  }

  return <Alert severity="success">{getSuccessText(query.data)}</Alert>;
}

export function StatusCard({ title, query, getSuccessText, waitingText }) {
  return (
    <Card component="section" aria-label={title}>
      <CardContent>
        <Stack sx={{ gap: 2 }}>
          <Typography variant="h6" component="h2">
            {title}
          </Typography>
          {renderStatus({ title, query, getSuccessText, waitingText })}
        </Stack>
      </CardContent>
    </Card>
  );
}
