import { zodResolver } from '@hookform/resolvers/zod';
import ArrowBack from '@mui/icons-material/ArrowBack';
import CreditCardOutlined from '@mui/icons-material/CreditCardOutlined';
import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Grid from '@mui/material/Grid';
import InputAdornment from '@mui/material/InputAdornment';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useMemo } from 'react';
import { useForm } from 'react-hook-form';
import { FormTextField } from '@/components/form/FormTextField';
import { paymentSchema } from '@/features/checkout/schemas';

export const PAYMENT_FORM_ID = 'checkout-payment-form';

function toFormErrors(fieldErrors) {
  return Object.fromEntries(
    Object.entries(fieldErrors ?? {}).map(([field, message]) => [
      field,
      { type: 'server', message },
    ]),
  );
}

export function PaymentStep({ payment, fieldErrors, primaryInline, onConfirm, onBack }) {
  const errors = useMemo(() => toFormErrors(fieldErrors), [fieldErrors]);
  const { control, handleSubmit } = useForm({
    resolver: zodResolver(paymentSchema),
    defaultValues: payment,
    errors,
  });

  return (
    <Card component="section" aria-labelledby="payment-step-title">
      <CardContent>
        <Stack
          component="form"
          id={PAYMENT_FORM_ID}
          noValidate
          onSubmit={handleSubmit(onConfirm)}
          sx={{ gap: 3 }}
        >
          <Typography id="payment-step-title" variant="h4" component="h2" tabIndex={-1}>
            Dati della carta
          </Typography>
          <Alert severity="info">Ambiente dimostrativo: nessun addebito reale.</Alert>
          <Grid container spacing={2}>
            <Grid size={12}>
              <FormTextField
                name="holder"
                control={control}
                label="Intestatario della carta"
                autoComplete="cc-name"
                fullWidth
              />
            </Grid>
            <Grid size={12}>
              <FormTextField
                name="number"
                control={control}
                label="Numero della carta"
                autoComplete="cc-number"
                fullWidth
                slotProps={{
                  htmlInput: { inputMode: 'numeric', maxLength: 23 },
                  input: {
                    startAdornment: (
                      <InputAdornment position="start">
                        <CreditCardOutlined fontSize="small" />
                      </InputAdornment>
                    ),
                  },
                }}
              />
            </Grid>
            <Grid size={6}>
              <FormTextField
                name="expiry"
                control={control}
                label="Scadenza"
                helperText="MM/AA"
                autoComplete="cc-exp"
                fullWidth
                slotProps={{ htmlInput: { inputMode: 'numeric', maxLength: 5 } }}
              />
            </Grid>
            <Grid size={6}>
              <FormTextField
                name="cvc"
                control={control}
                label="CVC"
                helperText="3 cifre sul retro"
                autoComplete="cc-csc"
                fullWidth
                slotProps={{ htmlInput: { inputMode: 'numeric', maxLength: 3 } }}
              />
            </Grid>
          </Grid>
          <Stack direction="row" sx={{ justifyContent: 'space-between', gap: 2 }}>
            <Button startIcon={<ArrowBack />} color="inherit" onClick={onBack}>
              Indietro
            </Button>
            {primaryInline ? (
              <Button type="submit" variant="contained" size="large">
                Continua al riepilogo
              </Button>
            ) : null}
          </Stack>
        </Stack>
      </CardContent>
    </Card>
  );
}
