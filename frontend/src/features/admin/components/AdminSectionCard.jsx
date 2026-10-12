import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useId } from 'react';

export function AdminSectionCard({ title, aside, children, disableContentPadding = false }) {
  const titleId = useId();

  return (
    <Card component="section" aria-labelledby={titleId}>
      <Stack
        direction="row"
        sx={{
          alignItems: 'baseline',
          justifyContent: 'space-between',
          gap: 2,
          flexWrap: 'wrap',
          px: 3,
          pt: 3,
          pb: disableContentPadding ? 2 : 0,
        }}
      >
        <Typography id={titleId} variant="h5" component="h3">
          {title}
        </Typography>
        {aside}
      </Stack>
      {disableContentPadding ? (
        children
      ) : (
        <CardContent sx={{ px: 3, pb: 3 }}>{children}</CardContent>
      )}
    </Card>
  );
}
