import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';

export function AuthHeader({ title, children }) {
  return (
    <Stack sx={{ gap: 1 }}>
      <Typography variant="h3" component="h1">
        {title}
      </Typography>
      {children ? (
        <Typography variant="body2" component="div" sx={{ color: 'text.secondary' }}>
          {children}
        </Typography>
      ) : null}
    </Stack>
  );
}
