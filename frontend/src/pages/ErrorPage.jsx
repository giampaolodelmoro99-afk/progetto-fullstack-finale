import ErrorOutlineOutlined from '@mui/icons-material/ErrorOutlineOutlined';
import Button from '@mui/material/Button';
import Container from '@mui/material/Container';
import { EmptyState } from '@/components/feedback/EmptyState';
import { paths } from '@/app/router/paths';

export default function ErrorPage() {
  return (
    <Container maxWidth="md" sx={{ py: { xs: 4, md: 8 } }}>
      <EmptyState
        icon={<ErrorOutlineOutlined />}
        title="Qualcosa non ha funzionato"
        titleComponent="h1"
        description="Si è verificato un errore imprevisto. Ricarica la pagina o torna alla home."
        action={
          <Button variant="contained" href={paths.home}>
            Torna alla home
          </Button>
        }
      />
    </Container>
  );
}
