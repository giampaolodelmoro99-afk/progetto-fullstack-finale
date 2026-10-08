import Grid from '@mui/material/Grid';
import Stack from '@mui/material/Stack';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableRow from '@mui/material/TableRow';
import Typography from '@mui/material/Typography';

function paragraphs(text) {
  return (text ?? '')
    .split(/\n\s*\n/)
    .map((paragraph) => paragraph.trim())
    .filter((paragraph) => paragraph !== '');
}

export function ProductDetails({ product }) {
  const descriptionParagraphs = paragraphs(product.description);
  const specifications = product.specifications ?? [];

  if (descriptionParagraphs.length === 0 && specifications.length === 0) {
    return null;
  }

  return (
    <Grid container spacing={{ xs: 5, md: 8 }}>
      {descriptionParagraphs.length > 0 ? (
        <Grid
          component="section"
          aria-labelledby="product-description-title"
          size={{ xs: 12, md: 6 }}
        >
          <Stack sx={{ gap: 2 }}>
            <Typography id="product-description-title" variant="h4" component="h2">
              Descrizione
            </Typography>
            {descriptionParagraphs.map((paragraph, index) => (
              <Typography key={index} variant="body2">
                {paragraph}
              </Typography>
            ))}
          </Stack>
        </Grid>
      ) : null}
      {specifications.length > 0 ? (
        <Grid
          component="section"
          aria-labelledby="product-specifications-title"
          size={{ xs: 12, md: 6 }}
        >
          <Stack sx={{ gap: 2 }}>
            <Typography id="product-specifications-title" variant="h4" component="h2">
              Scheda tecnica
            </Typography>
            <Table size="small" aria-labelledby="product-specifications-title">
              <TableBody>
                {specifications.map((specification, index) => (
                  <TableRow key={index}>
                    <TableCell
                      component="th"
                      scope="row"
                      sx={{ color: 'text.secondary', typography: 'body2', width: 0.4, pl: 0 }}
                    >
                      {specification.label}
                    </TableCell>
                    <TableCell sx={{ typography: 'body2', pr: 0 }}>{specification.value}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </Stack>
        </Grid>
      ) : null}
    </Grid>
  );
}
