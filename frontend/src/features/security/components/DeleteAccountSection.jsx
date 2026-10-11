import DeleteOutlined from '@mui/icons-material/DeleteOutlined';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useState } from 'react';
import { DeleteAccountDialog } from '@/features/security/components/DeleteAccountDialog';

export function DeleteAccountSection() {
  const [open, setOpen] = useState(false);

  return (
    <Card component="section" aria-labelledby="delete-account-title">
      <CardContent>
        <Stack
          direction={{ xs: 'column', sm: 'row' }}
          sx={{ gap: 2, justifyContent: 'space-between', alignItems: { sm: 'center' } }}
        >
          <Stack sx={{ gap: 0.5 }}>
            <Typography id="delete-account-title" variant="h5" component="h3">
              Elimina account
            </Typography>
            <Typography variant="body2" sx={{ color: 'text.secondary' }}>
              Cancelli i tuoi dati, gli indirizzi e l&apos;accesso. L&apos;operazione non si può
              annullare.
            </Typography>
          </Stack>
          <Button
            variant="outlined"
            color="error"
            startIcon={<DeleteOutlined />}
            onClick={() => setOpen(true)}
            sx={{ flexShrink: 0, width: { xs: 1, sm: 'auto' } }}
          >
            Elimina l&apos;account
          </Button>
        </Stack>
      </CardContent>
      {open ? <DeleteAccountDialog onClose={() => setOpen(false)} /> : null}
    </Card>
  );
}
