import CategoryOutlined from '@mui/icons-material/CategoryOutlined';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import Container from '@mui/material/Container';
import Stack from '@mui/material/Stack';
import { Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';
import { EmptyState } from '@/components/feedback/EmptyState';
import { CatalogBreadcrumbs } from '@/features/catalog/components/CatalogBreadcrumbs';

export function CategoryNotFound() {
  return (
    <Container maxWidth="lg" sx={{ py: { xs: 3, md: 4 } }}>
      <Stack sx={{ gap: 3 }}>
        <CatalogBreadcrumbs items={[{ label: 'Categoria non trovata' }]} />
        <Card>
          <EmptyState
            icon={<CategoryOutlined />}
            title="Questa categoria non esiste"
            titleComponent="h1"
            description="Forse il link è vecchio, oppure l'indirizzo ha un errore."
            action={
              <Button variant="contained" component={RouterLink} to={paths.products}>
                Vedi tutti i prodotti
              </Button>
            }
          />
        </Card>
      </Stack>
    </Container>
  );
}
