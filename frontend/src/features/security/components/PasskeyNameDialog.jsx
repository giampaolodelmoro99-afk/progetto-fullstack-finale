import { zodResolver } from '@hookform/resolvers/zod';
import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogContentText from '@mui/material/DialogContentText';
import DialogTitle from '@mui/material/DialogTitle';
import { useId } from 'react';
import { useForm } from 'react-hook-form';
import { FormTextField } from '@/components/form/FormTextField';
import { passkeyNameSchema } from '@/features/security/schemas';

export function PasskeyNameDialog({
  title,
  description = null,
  initialName = '',
  submitLabel,
  pending,
  error,
  onSubmit,
  onClose,
}) {
  const titleId = useId();
  const { control, handleSubmit, setError } = useForm({
    resolver: zodResolver(passkeyNameSchema),
    defaultValues: { name: initialName },
  });

  function close() {
    if (!pending) {
      onClose();
    }
  }

  return (
    <Dialog
      open
      onClose={close}
      aria-labelledby={titleId}
      fullWidth
      maxWidth="xs"
      slotProps={{
        paper: {
          component: 'form',
          noValidate: true,
          onSubmit: handleSubmit(({ name }) =>
            onSubmit(name, (message) => setError('name', { type: 'server', message })),
          ),
        },
      }}
    >
      <DialogTitle id={titleId}>{title}</DialogTitle>
      <DialogContent sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
        {description ? <DialogContentText variant="body2">{description}</DialogContentText> : null}
        {error ? <Alert severity="error">{error}</Alert> : null}
        <FormTextField
          name="name"
          control={control}
          label="Nome della passkey"
          helperText="Ad esempio iPhone di Giulia"
          autoComplete="off"
          autoFocus
          fullWidth
          sx={{ mt: 1 }}
        />
      </DialogContent>
      <DialogActions>
        <Button onClick={close} disabled={pending}>
          Annulla
        </Button>
        <Button type="submit" variant="contained" loading={pending}>
          {submitLabel}
        </Button>
      </DialogActions>
    </Dialog>
  );
}
