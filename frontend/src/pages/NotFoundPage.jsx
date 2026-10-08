import SearchOffOutlined from '@mui/icons-material/SearchOffOutlined';
import Button from '@mui/material/Button';
import Container from '@mui/material/Container';
import { Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';
import { EmptyState } from '@/components/feedback/EmptyState';

export default function NotFoundPage() {
  return (
    <Container maxWidth="md" sx={{ py: { xs: 4, md: 8 } }}>
      <EmptyState
        icon={<SearchOffOutlined />}
        title="Pagina non trovata"
        titleComponent="h1"
        description="L'indirizzo che hai aperto non esiste o non è più disponibile."
        action={
          <Button variant="contained" component={RouterLink} to={paths.home}>
            Torna alla home
          </Button>
        }
      />
    </Container>
  );
}
