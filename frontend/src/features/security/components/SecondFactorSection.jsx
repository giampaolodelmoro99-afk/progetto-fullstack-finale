import FormHelperText from '@mui/material/FormHelperText';
import Link from '@mui/material/Link';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { FormTextField } from '@/components/form/FormTextField';
import { usePasskeySupport } from '@/features/security/hooks/usePasskeySupport';
import { SECOND_FACTOR_METHODS } from '@/features/security/utils/secondFactor';

const SWITCH_LABELS = {
  [SECOND_FACTOR_METHODS.totp]: "Usa il codice dell'app",
  [SECOND_FACTOR_METHODS.recoveryCode]: 'Usa un codice di recupero',
  [SECOND_FACTOR_METHODS.passkey]: 'Usa una passkey',
};

export function SecondFactorSection({ control, method, onMethodChange, methods, credentialError }) {
  const passkeySupported = usePasskeySupport();
  const available = Object.values(SECOND_FACTOR_METHODS).filter(
    (candidate) =>
      methods.includes(candidate) &&
      (candidate !== SECOND_FACTOR_METHODS.passkey || passkeySupported),
  );
  const alternatives = available.filter((candidate) => candidate !== method);

  return (
    <Stack sx={{ gap: 2 }}>
      {method === SECOND_FACTOR_METHODS.totp ? (
        <FormTextField
          key="totp"
          name="code"
          control={control}
          label="Codice a 6 cifre"
          helperText="Il codice cambia ogni 30 secondi."
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
          helperText="Ogni codice si può usare una sola volta."
          autoComplete="one-time-code"
          fullWidth
        />
      ) : null}
      {method === SECOND_FACTOR_METHODS.passkey ? (
        <Stack sx={{ gap: 0.5 }}>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Il dispositivo ti chiederà l'impronta, il volto o il PIN.
          </Typography>
          {credentialError ? <FormHelperText error>{credentialError}</FormHelperText> : null}
        </Stack>
      ) : null}
      {alternatives.length > 0 ? (
        <Stack direction="row" sx={{ flexWrap: 'wrap', columnGap: 3, rowGap: 1 }}>
          {alternatives.map((candidate) => (
            <Link
              key={candidate}
              component="button"
              type="button"
              variant="body2"
              onClick={() => onMethodChange(candidate)}
            >
              {SWITCH_LABELS[candidate]}
            </Link>
          ))}
        </Stack>
      ) : null}
    </Stack>
  );
}
