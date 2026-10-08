import Container from '@mui/material/Container';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';

export function InfoPageLayout({ title, description, children }) {
  return (
    <Container maxWidth="md" sx={{ py: { xs: 4, md: 8 } }}>
      <Stack sx={{ gap: { xs: 4, md: 5 } }}>
        <Stack sx={{ gap: 1 }}>
          <Typography variant="h2" component="h1">
            {title}
          </Typography>
          {description ? (
            <Typography variant="body1" sx={{ color: 'text.secondary' }}>
              {description}
            </Typography>
          ) : null}
        </Stack>
        {children}
      </Stack>
    </Container>
  );
}
