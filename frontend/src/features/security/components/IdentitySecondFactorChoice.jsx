import Button from '@mui/material/Button';
import Divider from '@mui/material/Divider';
import FormHelperText from '@mui/material/FormHelperText';
import Stack from '@mui/material/Stack';
import ToggleButton from '@mui/material/ToggleButton';
import ToggleButtonGroup from '@mui/material/ToggleButtonGroup';
import Typography from '@mui/material/Typography';
import { FormTextField } from '@/components/form/FormTextField';
import { SECOND_FACTOR_METHODS } from '@/features/security/utils/secondFactor';

export function IdentitySecondFactorChoice({
  control,
  method,
  onMethodChange,
  recoveryCodesRemaining,
  passkeyAllowed,
  credentialError,
}) {
  return (
    <Stack sx={{ gap: 2 }}>
      <Divider />
      <Typography variant="subtitle2" component="h3">
        Secondo fattore
      </Typography>
      <ToggleButtonGroup
        exclusive
        fullWidth
        size="small"
        value={method}
        onChange={(event, value) => value && onMethodChange(value)}
        aria-label="Secondo fattore"
      >
        <ToggleButton value={SECOND_FACTOR_METHODS.totp}>App</ToggleButton>
        <ToggleButton value={SECOND_FACTOR_METHODS.recoveryCode}>Codice di recupero</ToggleButton>
        {passkeyAllowed ? (
          <ToggleButton value={SECOND_FACTOR_METHODS.passkey}>Passkey</ToggleButton>
        ) : null}
      </ToggleButtonGroup>
      {method === SECOND_FACTOR_METHODS.totp ? (
        <FormTextField
          key="totp"
          name="code"
          control={control}
          label="Codice a 6 cifre"
          helperText="Lo trovi nell'app di autenticazione."
          autoComplete="one-time-code"
          slotProps={{ htmlInput: { inputMode: 'numeric' } }}
          fullWidth
        />
      ) : null}
      {method === SECOND_FACTOR_METHODS.recoveryCode ? (
        <FormTextField
          key="recovery-code"
          name="code"
          control={control}
          label="Codice di recupero"
          helperText={`Ogni codice si può usare una sola volta. Te ne restano ${recoveryCodesRemaining}.`}
          autoComplete="one-time-code"
          fullWidth
        />
      ) : null}
      {method === SECOND_FACTOR_METHODS.passkey ? (
        <Stack sx={{ gap: 1, alignItems: 'flex-start' }}>
          <Button type="submit" variant="outlined">
            Usa la passkey
          </Button>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Il dispositivo ti chiederà l'impronta, il volto o il PIN.
          </Typography>
          {credentialError ? <FormHelperText error>{credentialError}</FormHelperText> : null}
        </Stack>
      ) : null}
    </Stack>
  );
}
