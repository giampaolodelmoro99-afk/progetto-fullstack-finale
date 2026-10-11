import { zodResolver } from '@hookform/resolvers/zod';
import ContentCopyOutlined from '@mui/icons-material/ContentCopyOutlined';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import IconButton from '@mui/material/IconButton';
import Stack from '@mui/material/Stack';
import Step from '@mui/material/Step';
import StepLabel from '@mui/material/StepLabel';
import Stepper from '@mui/material/Stepper';
import Typography from '@mui/material/Typography';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { TooManyAttemptsAlert } from '@/components/feedback/TooManyAttemptsAlert';
import { FormTextField } from '@/components/form/FormTextField';
import { RecoveryCodesPanel } from '@/features/security/components/RecoveryCodesPanel';
import { useConfirmTwoFactor } from '@/features/security/hooks/useConfirmTwoFactor';
import { useCopyToClipboard } from '@/features/security/hooks/useCopyToClipboard';
import { totpCodeSchema } from '@/features/security/schemas';
import { useIsDesktop } from '@/hooks/useIsDesktop';
import { useNotify } from '@/hooks/useNotify';
import { useRateLimit } from '@/hooks/useRateLimit';
import { applyFieldErrors } from '@/utils/applyFieldErrors';

const STEPS = ['Scansiona il codice QR', 'Verifica il codice', 'Salva i codici di recupero'];

const SETUP_CONFLICTS = new Set(['two_factor_setup_missing', 'two_factor_already_enabled']);

function groupKey(secret) {
  return secret.match(/.{1,4}/g)?.join(' ') ?? secret;
}

function qrCodeSource(svg) {
  return `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`;
}

function ScanStep({ setup, onCancel, onContinue }) {
  const copy = useCopyToClipboard();

  return (
    <Stack sx={{ gap: 2 }}>
      <Typography variant="body2">
        Apri l&apos;app di autenticazione sul telefono, aggiungi un account e inquadra il codice QR.
      </Typography>
      <Box
        component="img"
        src={qrCodeSource(setup.qr_code_svg)}
        alt="Codice QR da inquadrare con l'app di autenticazione"
        sx={{
          width: (theme) => theme.layout.qrCodeSize,
          height: (theme) => theme.layout.qrCodeSize,
          p: 1,
          bgcolor: 'background.paper',
          border: 1,
          borderColor: 'divider',
          borderRadius: 1,
        }}
      />
      <Stack sx={{ gap: 0.5 }}>
        <Typography variant="body2" sx={{ color: 'text.secondary' }}>
          Non puoi inquadrarlo? Inserisci questa chiave a mano:
        </Typography>
        <Stack direction="row" sx={{ alignItems: 'center', gap: 1 }}>
          <Typography variant="code" sx={{ overflowWrap: 'anywhere' }}>
            {groupKey(setup.secret)}
          </Typography>
          <IconButton
            aria-label="Copia la chiave"
            size="small"
            onClick={() => copy(setup.secret, 'Chiave copiata')}
          >
            <ContentCopyOutlined fontSize="small" />
          </IconButton>
        </Stack>
      </Stack>
      <Stack direction={{ xs: 'column-reverse', sm: 'row' }} sx={{ gap: 1 }}>
        <Button onClick={onCancel}>Annulla</Button>
        <Button variant="contained" onClick={onContinue}>
          Continua
        </Button>
      </Stack>
    </Stack>
  );
}

function VerifyStep({ onBack, onVerified, onConflict }) {
  const confirmTwoFactor = useConfirmTwoFactor();
  const rateLimit = useRateLimit();
  const [formError, setFormError] = useState(null);
  const { control, handleSubmit, setError } = useForm({
    resolver: zodResolver(totpCodeSchema),
    defaultValues: { code: '' },
  });

  function submit({ code }) {
    setFormError(null);
    rateLimit.clear();
    confirmTwoFactor.mutate(code, {
      onSuccess: onVerified,
      onError: (error) => {
        if (error.status === 429) {
          rateLimit.trigger(error);
          return;
        }

        if (SETUP_CONFLICTS.has(error.code)) {
          onConflict(error);
          return;
        }

        if (!applyFieldErrors(setError, error.fieldErrors, ['code'])) {
          setFormError(error.message);
        }
      },
    });
  }

  return (
    <Stack component="form" noValidate onSubmit={handleSubmit(submit)} sx={{ gap: 2 }}>
      <Typography variant="body2">
        Inserisci il codice a 6 cifre che vedi ora nell&apos;app, accanto a Casa del Silicio.
      </Typography>
      <TooManyAttemptsAlert rateLimit={rateLimit} />
      {formError ? <Alert severity="error">{formError}</Alert> : null}
      <Box sx={{ width: 1, maxWidth: { sm: 0.5 } }}>
        <FormTextField
          name="code"
          control={control}
          label="Codice a 6 cifre"
          helperText="Il codice cambia ogni 30 secondi."
          autoComplete="one-time-code"
          autoFocus
          slotProps={{ htmlInput: { inputMode: 'numeric' } }}
          fullWidth
        />
      </Box>
      <Stack direction={{ xs: 'column-reverse', sm: 'row' }} sx={{ gap: 1 }}>
        <Button onClick={onBack} disabled={confirmTwoFactor.isPending}>
          Indietro
        </Button>
        <Button
          type="submit"
          variant="contained"
          loading={confirmTwoFactor.isPending}
          disabled={rateLimit.isBlocking}
        >
          Verifica
        </Button>
      </Stack>
    </Stack>
  );
}

export function TwoFactorSetup({ setup, onClose }) {
  const isDesktop = useIsDesktop();
  const notify = useNotify();
  const [step, setStep] = useState(0);
  const [recoveryCodes, setRecoveryCodes] = useState(null);

  function handleVerified(codes) {
    setRecoveryCodes(codes);
    setStep(2);
  }

  function handleConflict(error) {
    notify({ message: error.message, severity: 'error' });
    onClose();
  }

  return (
    <Stack sx={{ gap: 3 }}>
      <Typography id="two-factor-card-title" variant="h5" component="h3">
        Attiva la doppia autenticazione
      </Typography>
      <Stepper activeStep={step} orientation={isDesktop ? 'horizontal' : 'vertical'}>
        {STEPS.map((label) => (
          <Step key={label}>
            <StepLabel>{label}</StepLabel>
          </Step>
        ))}
      </Stepper>
      {step === 0 ? (
        <ScanStep setup={setup} onCancel={onClose} onContinue={() => setStep(1)} />
      ) : null}
      {step === 1 ? (
        <VerifyStep
          onBack={() => setStep(0)}
          onVerified={handleVerified}
          onConflict={handleConflict}
        />
      ) : null}
      {step === 2 ? (
        <RecoveryCodesPanel
          codes={recoveryCodes}
          onDone={() => {
            onClose();
            notify({ message: 'Doppia autenticazione attivata' });
          }}
        />
      ) : null}
    </Stack>
  );
}
