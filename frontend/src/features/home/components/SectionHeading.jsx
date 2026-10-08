import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';

export function SectionHeading({ id, overline, title, description }) {
  return (
    <Stack sx={{ gap: 1 }}>
      {overline ? (
        <Typography variant="overline" component="p" sx={{ color: 'text.secondary' }}>
          {overline}
        </Typography>
      ) : null}
      <Typography id={id} variant="h2" component="h2">
        {title}
      </Typography>
      {description ? (
        <Typography variant="body2" sx={{ color: 'text.secondary' }}>
          {description}
        </Typography>
      ) : null}
    </Stack>
  );
}
