import { zodResolver } from '@hookform/resolvers/zod';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { FormTextField } from '@/components/form/FormTextField';
import { useUpdateProfile } from '@/features/account/hooks/useUpdateProfile';
import { profileSchema } from '@/features/account/schemas';
import { useNotify } from '@/hooks/useNotify';
import { applyFieldErrors } from '@/utils/applyFieldErrors';

export function PersonalDataCard({ user }) {
  const updateProfile = useUpdateProfile();
  const notify = useNotify();
  const [formError, setFormError] = useState(null);
  const { control, handleSubmit, setError, reset } = useForm({
    resolver: zodResolver(profileSchema),
    defaultValues: { name: user.name },
  });

  function submit(values) {
    setFormError(null);
    updateProfile.mutate(values, {
      onSuccess: (updated) => {
        reset({ name: updated.name });
        notify({ message: 'Modifiche salvate' });
      },
      onError: (error) => {
        if (!applyFieldErrors(setError, error.fieldErrors, ['name'])) {
          setFormError(error.message);
        }
      },
    });
  }

  return (
    <Card component="section" aria-labelledby="personal-data-title">
      <CardContent>
        <Stack
          component="form"
          noValidate
          onSubmit={handleSubmit(submit)}
          sx={{ gap: 3, alignItems: 'flex-start' }}
        >
          <Typography id="personal-data-title" variant="h5" component="h3">
            Dati personali
          </Typography>
          {formError ? (
            <Alert severity="error" sx={{ width: 1 }}>
              {formError}
            </Alert>
          ) : null}
          <Box sx={{ width: 1, maxWidth: { md: 0.6 } }}>
            <FormTextField
              name="name"
              control={control}
              label="Nome e cognome"
              autoComplete="name"
            />
          </Box>
          <Button
            type="submit"
            variant="contained"
            loading={updateProfile.isPending}
            sx={{ width: { xs: 1, sm: 'auto' } }}
          >
            Salva le modifiche
          </Button>
        </Stack>
      </CardContent>
    </Card>
  );
}
