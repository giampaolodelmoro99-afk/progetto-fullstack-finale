import Stack from '@mui/material/Stack';
import { AuthHeader } from '@/features/auth/components/AuthHeader';

export function AuthResult({ title, children, actions }) {
  return (
    <Stack sx={{ gap: 3 }}>
      <AuthHeader title={title}>{children}</AuthHeader>
      {actions ? <Stack sx={{ gap: 1.5 }}>{actions}</Stack> : null}
    </Stack>
  );
}
