import { zodResolver } from '@hookform/resolvers/zod';
import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import Checkbox from '@mui/material/Checkbox';
import FormControlLabel from '@mui/material/FormControlLabel';
import Grid from '@mui/material/Grid';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import { useState } from 'react';
import { Controller, useForm } from 'react-hook-form';
import { FormTextField } from '@/components/form/FormTextField';
import { useCreateAddress } from '@/features/account/hooks/useCreateAddress';
import { useUpdateAddress } from '@/features/account/hooks/useUpdateAddress';
import { addressSchema, emptyAddress } from '@/features/account/schemas';
import { applyFieldErrors } from '@/utils/applyFieldErrors';

const FIELDS = Object.keys(emptyAddress);

function initialValues(address) {
  if (!address) {
    return emptyAddress;
  }

  return Object.fromEntries(FIELDS.map((field) => [field, address[field]]));
}

export function AddressForm({
  address = null,
  title = address ? 'Modifica indirizzo' : 'Nuovo indirizzo',
  showTitle = true,
  onSaved,
  onCancel,
}) {
  const createAddress = useCreateAddress();
  const updateAddress = useUpdateAddress();
  const saveAddress = address ? updateAddress : createAddress;
  const [formError, setFormError] = useState(null);
  const { control, handleSubmit, setError } = useForm({
    resolver: zodResolver(addressSchema),
    defaultValues: initialValues(address),
  });

  function submit(values) {
    setFormError(null);
    saveAddress.mutate(address ? { id: address.id, ...values } : values, {
      onSuccess: onSaved,
      onError: (error) => {
        if (!applyFieldErrors(setError, error.fieldErrors, FIELDS)) {
          setFormError(error.message);
        }
      },
    });
  }

  return (
    <Stack
      component="form"
      noValidate
      aria-label={title}
      onSubmit={handleSubmit(submit)}
      sx={{ gap: 2 }}
    >
      {showTitle ? (
        <Typography variant="subtitle1" component="h3">
          {title}
        </Typography>
      ) : null}
      {formError ? <Alert severity="error">{formError}</Alert> : null}
      <Grid container spacing={2}>
        <Grid size={12}>
          <FormTextField
            name="full_name"
            control={control}
            label="Nome e cognome"
            autoComplete="name"
            fullWidth
          />
        </Grid>
        <Grid size={12}>
          <FormTextField
            name="street"
            control={control}
            label="Via e numero civico"
            autoComplete="address-line1"
            fullWidth
          />
        </Grid>
        <Grid size={{ xs: 12, md: 6 }}>
          <FormTextField
            name="city"
            control={control}
            label="Città"
            autoComplete="address-level2"
            fullWidth
          />
        </Grid>
        <Grid size={{ xs: 6, md: 3 }}>
          <FormTextField
            name="postal_code"
            control={control}
            label="CAP"
            autoComplete="postal-code"
            slotProps={{ htmlInput: { inputMode: 'numeric', maxLength: 5 } }}
            fullWidth
          />
        </Grid>
        <Grid size={{ xs: 6, md: 3 }}>
          <FormTextField
            name="province"
            control={control}
            label="Provincia"
            autoComplete="address-level1"
            slotProps={{ htmlInput: { maxLength: 2 } }}
            fullWidth
          />
        </Grid>
        <Grid size={{ xs: 12, md: 6 }}>
          <TextField label="Paese" value="Italia" disabled fullWidth />
        </Grid>
        <Grid size={{ xs: 12, md: 6 }}>
          <FormTextField
            name="phone"
            control={control}
            label="Telefono"
            type="tel"
            autoComplete="tel"
            fullWidth
          />
        </Grid>
      </Grid>
      {address?.is_default ? null : (
        <Controller
          name="is_default"
          control={control}
          render={({ field: { value, onChange, ref, ...field } }) => (
            <FormControlLabel
              control={
                <Checkbox
                  {...field}
                  slotProps={{ input: { ref } }}
                  checked={value}
                  onChange={(event) => onChange(event.target.checked)}
                />
              }
              label="Imposta come predefinito"
            />
          )}
        />
      )}
      <Stack direction="row" sx={{ gap: 1.5, justifyContent: 'flex-end' }}>
        {onCancel ? (
          <Button variant="outlined" onClick={onCancel} disabled={saveAddress.isPending}>
            Annulla
          </Button>
        ) : null}
        <Button type="submit" variant="contained" loading={saveAddress.isPending}>
          Salva indirizzo
        </Button>
      </Stack>
    </Stack>
  );
}
