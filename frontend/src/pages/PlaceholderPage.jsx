import Container from '@mui/material/Container';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useRouteTitle } from '@/app/router/useRouteTitle';

const HEADING_VARIANTS = {
  h1: 'h2',
  h2: 'h3',
};

export default function PlaceholderPage({ headingComponent = 'h1', contained = false }) {
  const title = useRouteTitle();
  const content = (
    <Stack sx={{ gap: 1 }}>
      {headingComponent ? (
        <Typography variant={HEADING_VARIANTS[headingComponent]} component={headingComponent}>
          {title}
        </Typography>
      ) : null}
      <Typography variant="body1" sx={{ color: 'text.secondary' }}>
        Questa pagina arriva presto.
      </Typography>
    </Stack>
  );

  if (!contained) {
    return content;
  }

  return (
    <Container maxWidth="lg" sx={{ py: { xs: 4, md: 8 } }}>
      {content}
    </Container>
  );
}
