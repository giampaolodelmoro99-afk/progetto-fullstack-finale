import Box from '@mui/material/Box';
import Container from '@mui/material/Container';
import Divider from '@mui/material/Divider';
import Grid from '@mui/material/Grid';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { paths } from '@/app/router/paths';
import { Logo } from '@/components/navigation/Logo';
import { useCategories } from '@/features/catalog/hooks/useCategories';
import { legalSections, supportSections } from '@/features/info/sections';
import { FooterColumn } from '@/layouts/store/FooterColumn';

const supportLinks = [
  { label: 'Spedizioni e consegne', to: paths.supportSection(supportSections.shipping) },
  { label: 'Resi e rimborsi', to: paths.supportSection(supportSections.returns) },
  { label: 'Garanzia', to: paths.supportSection(supportSections.warranty) },
  { label: "Stato dell'ordine", to: paths.accountOrders },
  { label: 'Contatti', to: paths.supportSection(supportSections.contacts) },
];

const legalLinks = [
  { label: 'Condizioni di vendita', to: paths.legalSection(legalSections.termsOfSale) },
  { label: 'Diritto di recesso', to: paths.legalSection(legalSections.withdrawal) },
  { label: 'Privacy', to: paths.legalSection(legalSections.privacy) },
  { label: 'Cookie', to: paths.legalSection(legalSections.cookies) },
  { label: 'Note legali', to: paths.legal },
];

export function StoreFooter() {
  const categoriesQuery = useCategories();
  const categoryLinks = (categoriesQuery.data ?? []).map((category) => ({
    label: category.name,
    to: paths.category(category.slug),
  }));

  return (
    <Box component="footer" sx={{ bgcolor: 'background.inverse', color: 'text.inverse' }}>
      <Container maxWidth="lg" sx={{ py: { xs: 6, md: 8 } }}>
        <Grid container spacing={{ xs: 5, md: 4 }}>
          <Grid size={{ xs: 12, md: 3 }}>
            <Stack sx={{ gap: 2 }}>
              <Logo color="text.inverse" />
              <Typography
                variant="body2"
                sx={{ maxWidth: (theme) => theme.layout.footerTaglineMaxWidth }}
              >
                Elettronica e accessori tech della nostra linea, scelti per durare.
              </Typography>
            </Stack>
          </Grid>
          {categoriesQuery.isError ? null : (
            <Grid size={{ xs: 12, sm: 4, md: 3 }}>
              <FooterColumn
                title="Categorie"
                links={categoryLinks}
                loading={categoriesQuery.isPending}
              />
            </Grid>
          )}
          <Grid size={{ xs: 12, sm: 4, md: 3 }}>
            <FooterColumn title="Assistenza" links={supportLinks} />
          </Grid>
          <Grid size={{ xs: 12, sm: 4, md: 3 }}>
            <FooterColumn title="Note legali" links={legalLinks} />
          </Grid>
        </Grid>
        <Divider sx={{ borderColor: 'inverseDivider', mt: { xs: 5, md: 6 }, mb: 3 }} />
        <Stack
          direction={{ xs: 'column', sm: 'row' }}
          sx={{ justifyContent: 'space-between', gap: 1 }}
        >
          <Typography variant="caption">© {new Date().getFullYear()} Casa del Silicio</Typography>
          <Typography variant="caption">Prezzi IVA inclusa · Pagamenti protetti</Typography>
        </Stack>
      </Container>
    </Box>
  );
}
