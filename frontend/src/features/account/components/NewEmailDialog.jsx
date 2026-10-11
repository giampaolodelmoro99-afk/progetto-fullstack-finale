import { zodResolver } from '@hookform/resolvers/zod';
import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogTitle from '@mui/material/DialogTitle';
import { useId, useState } from 'react';
import { useForm } from 'react-hook-form';
import { FormTextField } from '@/components/form/FormTextField';
import { useRequestEmailChange } from '@/features/account/hooks/useRequestEmailChange';
import { newEmailSchema } from '@/features/account/schemas';
import { useNotify } from '@/hooks/useNotify';
import { applyFieldErrors } from '@/utils/applyFieldErrors';
import { IDENTITY_CONFIRMATION_CANCELLED } from '@/utils/normalizeApiError';

export function NewEmailDialog({ open, onClose }) {
  const titleId = useId();
  const requestEmailChange = useRequestEmailChange();
  const notify = useNotify();
  const [formError, setFormError] = useState(null);
  const { control, handleSubmit, setError, reset } = useForm({
    resolver: zodResolver(newEmailSchema),
    defaultValues: { email: '' },
  });

  function close() {
    if (requestEmailChange.isPending) {
      return;
    }

    reset();
    setFormError(null);
    onClose();
  }

  function submit({ email }) {
    setFormError(null);
    requestEmailChange.mutate(email, {
      onSuccess: () => {
        reset();
        onClose();
        notify({ message: `Ti abbiamo inviato un link a ${email}.` });
      },
      onError: (error) => {
        if (error.code === IDENTITY_CONFIRMATION_CANCELLED) {
          return;
        }

        if (!applyFieldErrors(setError, error.fieldErrors, ['email'])) {
          setFormError(error.message);
        }
      },
    });
  }

  return (
    <Dialog
      open={open}
      onClose={close}
      aria-labelledby={titleId}
      fullWidth
      maxWidth="xs"
      slotProps={{ paper: { component: 'form', noValidate: true, onSubmit: handleSubmit(submit) } }}
    >
      <DialogTitle id={titleId}>Cambia email</DialogTitle>
      <DialogContent sx={{ display: 'flex', flexDirection: 'column', gap: 2 }}>
        {formError ? <Alert severity="error">{formError}</Alert> : null}
        <FormTextField
          name="email"
          control={control}
          label="Nuova email"
          type="email"
          autoComplete="email"
          autoFocus
          sx={{ mt: 1 }}
        />
      </DialogContent>
      <DialogActions>
        <Button onClick={close} disabled={requestEmailChange.isPending}>
          Annulla
        </Button>
        <Button type="submit" variant="contained" loading={requestEmailChange.isPending}>
          Invia il link
        </Button>
      </DialogActions>
    </Dialog>
  );
}
