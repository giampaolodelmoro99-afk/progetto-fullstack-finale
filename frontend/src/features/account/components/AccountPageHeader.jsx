import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';

export function AccountPageHeader({ title, description }) {
  return (
    <Stack sx={{ gap: 0.5 }}>
      <Typography variant="h3" component="h2">
        {title}
      </Typography>
      <Typography variant="body2" sx={{ color: 'text.secondary' }}>
        {description}
      </Typography>
    </Stack>
  );
}
