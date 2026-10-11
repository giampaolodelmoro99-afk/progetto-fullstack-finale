import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useState } from 'react';
import { ChangePasswordForm } from '@/features/security/components/ChangePasswordForm';
import { formatDate } from '@/utils/format';

export function PasswordCard({ overview }) {
  const [editing, setEditing] = useState(false);

  return (
    <Card component="section" aria-labelledby="password-card-title">
      <CardContent>
        <Stack sx={{ gap: 3 }}>
          <Stack
            direction={{ xs: 'column', sm: 'row' }}
            sx={{ gap: 2, justifyContent: 'space-between', alignItems: { sm: 'center' } }}
          >
            <Stack sx={{ gap: 0.5 }}>
              <Typography id="password-card-title" variant="h5" component="h3">
                Password
              </Typography>
              {overview.password_changed_at ? (
                <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                  Ultima modifica: {formatDate(overview.password_changed_at)}
                </Typography>
              ) : null}
            </Stack>
            {editing ? null : (
              <Button
                variant="outlined"
                onClick={() => setEditing(true)}
                sx={{ flexShrink: 0, width: { xs: 1, sm: 'auto' } }}
              >
                Cambia password
              </Button>
            )}
          </Stack>
          {editing ? (
            <ChangePasswordForm
              key={String(overview.two_factor_enabled)}
              overview={overview}
              onClose={() => setEditing(false)}
            />
          ) : null}
        </Stack>
      </CardContent>
    </Card>
  );
}
