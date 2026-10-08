import Avatar from '@mui/material/Avatar';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';

export function EmptyState({ icon, title, description, action, titleComponent = 'h2' }) {
  return (
    <Stack sx={{ alignItems: 'center', textAlign: 'center', gap: 2, py: 6, px: 2 }}>
      {icon ? (
        <Avatar
          sx={{
            bgcolor: 'background.media',
            color: 'text.primary',
            width: (theme) => theme.spacing(7),
            height: (theme) => theme.spacing(7),
          }}
        >
          {icon}
        </Avatar>
      ) : null}
      <Typography variant="h5" component={titleComponent}>
        {title}
      </Typography>
      {description ? (
        <Typography
          variant="body2"
          sx={{ color: 'text.secondary', maxWidth: (theme) => theme.layout.emptyStateTextMaxWidth }}
        >
          {description}
        </Typography>
      ) : null}
      {action}
    </Stack>
  );
}
