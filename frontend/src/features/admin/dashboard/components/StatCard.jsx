import Card from '@mui/material/Card';
import CardActionArea from '@mui/material/CardActionArea';
import CardContent from '@mui/material/CardContent';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { Link as RouterLink } from 'react-router';

export function StatCard({ label, value, caption, to }) {
  const content = (
    <CardContent sx={{ height: 1 }}>
      <Stack sx={{ gap: 0.5 }}>
        <Typography variant="body2" component="p">
          {label}
        </Typography>
        {value}
        {caption ? (
          <Typography variant="caption" sx={{ color: 'text.secondary' }}>
            {caption}
          </Typography>
        ) : null}
      </Stack>
    </CardContent>
  );

  return (
    <Card sx={{ height: 1 }}>
      {to ? (
        <CardActionArea component={RouterLink} to={to} sx={{ height: 1, alignItems: 'flex-start' }}>
          {content}
        </CardActionArea>
      ) : (
        content
      )}
    </Card>
  );
}
