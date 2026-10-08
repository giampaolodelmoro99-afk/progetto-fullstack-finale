import Container from '@mui/material/Container';
import { ErrorState } from '@/components/feedback/ErrorState';
import { useAuth } from '@/features/auth/hooks/useAuth';

export function UserLoadError() {
  const { refetch } = useAuth();

  return (
    <Container maxWidth="sm" sx={{ py: { xs: 4, md: 8 } }}>
      <ErrorState message="Non è stato possibile caricare il tuo account." onRetry={refetch} />
    </Container>
  );
}
