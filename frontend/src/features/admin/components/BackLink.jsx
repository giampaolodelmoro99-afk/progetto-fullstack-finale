import ArrowBack from '@mui/icons-material/ArrowBack';
import Button from '@mui/material/Button';
import { Link as RouterLink } from 'react-router';

export function BackLink({ to, children }) {
  return (
    <Button component={RouterLink} to={to} startIcon={<ArrowBack />} sx={{ ml: -1 }}>
      {children}
    </Button>
  );
}
