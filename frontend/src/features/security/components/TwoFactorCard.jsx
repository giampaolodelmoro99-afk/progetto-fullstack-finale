import Check from '@mui/icons-material/Check';
import PhonelinkLockOutlined from '@mui/icons-material/PhonelinkLockOutlined';
import VerifiedUserOutlined from '@mui/icons-material/VerifiedUserOutlined';
import Avatar from '@mui/material/Avatar';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Chip from '@mui/material/Chip';
import Divider from '@mui/material/Divider';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useQueryClient } from '@tanstack/react-query';
import { useState } from 'react';
import { ConfirmActionDialog } from '@/features/security/components/ConfirmActionDialog';
import { RecoveryCodesPanel } from '@/features/security/components/RecoveryCodesPanel';
import { TwoFactorSetup } from '@/features/security/components/TwoFactorSetup';
import { useDisableTwoFactor } from '@/features/security/hooks/useDisableTwoFactor';
import { useRegenerateRecoveryCodes } from '@/features/security/hooks/useRegenerateRecoveryCodes';
import { useStartTwoFactorSetup } from '@/features/security/hooks/useStartTwoFactorSetup';
import { visibleActionError } from '@/features/security/utils/actionError';
import { refreshSecurityQueries } from '@/features/security/utils/refreshSecurityQueries';
import { useNotify } from '@/hooks/useNotify';
import { formatDate } from '@/utils/format';
import { IDENTITY_CONFIRMATION_CANCELLED } from '@/utils/normalizeApiError';

function FactorRow({ icon, title, description, action }) {
  return (
    <Stack
      direction={{ xs: 'column', sm: 'row' }}
      sx={{ gap: 2, justifyContent: 'space-between', alignItems: { sm: 'center' } }}
    >
      <Stack direction="row" sx={{ gap: 2, alignItems: 'center' }}>
        <Avatar sx={{ bgcolor: 'background.media', color: 'text.primary' }}>{icon}</Avatar>
        <Stack>
          <Typography variant="subtitle2" component="p">
            {title}
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            {description}
          </Typography>
        </Stack>
      </Stack>
      {action}
    </Stack>
  );
}

function DisabledContent({ onStart, starting }) {
  return (
    <Stack sx={{ gap: 2, alignItems: 'flex-start' }}>
      <Stack direction="row" sx={{ gap: 1.5, alignItems: 'center', flexWrap: 'wrap' }}>
        <Typography id="two-factor-card-title" variant="h5" component="h3">
          Doppia autenticazione
        </Typography>
        <Chip size="small" label="Disattivata" />
      </Stack>
      <Typography variant="body2" sx={{ color: 'text.secondary' }}>
        Aggiungi un secondo passaggio all&apos;accesso: oltre alla password ti chiederemo un codice
        dall&apos;app di autenticazione o una passkey. Lo chiederemo anche per recuperare la
        password e per le modifiche importanti.
      </Typography>
      <Button
        variant="contained"
        onClick={onStart}
        loading={starting}
        sx={{ width: { xs: 1, sm: 'auto' } }}
      >
        Attiva
      </Button>
    </Stack>
  );
}

function EnabledContent({ overview, onRegenerate, onDisable }) {
  const since = formatDate(overview.two_factor_confirmed_at);

  return (
    <Stack sx={{ gap: 3 }}>
      <Stack sx={{ gap: 0.5 }}>
        <Stack direction="row" sx={{ gap: 1.5, alignItems: 'center', flexWrap: 'wrap' }}>
          <Typography id="two-factor-card-title" variant="h5" component="h3">
            Doppia autenticazione
          </Typography>
          <Chip
            size="small"
            variant="soft"
            color="success"
            icon={<Check fontSize="small" />}
            label="Attiva"
          />
        </Stack>
        <Typography variant="body2" sx={{ color: 'text.secondary' }}>
          Attiva dal {since}
        </Typography>
      </Stack>
      <FactorRow
        icon={<PhonelinkLockOutlined />}
        title="App di autenticazione"
        description={`Configurata il ${since}`}
      />
      <FactorRow
        icon={<VerifiedUserOutlined />}
        title="Codici di recupero"
        description={`Codici di recupero rimasti: ${overview.recovery_codes_remaining} di ${overview.recovery_codes_total}`}
        action={
          <Button
            variant="outlined"
            onClick={onRegenerate}
            sx={{ flexShrink: 0, width: { xs: 1, sm: 'auto' } }}
          >
            Genera nuovi codici
          </Button>
        }
      />
      <Divider />
      <Box>
        <Button color="error" onClick={onDisable} sx={{ px: 1 }}>
          Disattiva
        </Button>
      </Box>
    </Stack>
  );
}

function refreshAfterConflict(queryClient, error) {
  if (error.status === 409) {
    refreshSecurityQueries(queryClient);
  }
}

export function TwoFactorCard({ overview }) {
  const queryClient = useQueryClient();
  const notify = useNotify();
  const startSetup = useStartTwoFactorSetup();
  const regenerate = useRegenerateRecoveryCodes();
  const disable = useDisableTwoFactor();
  const [setup, setSetup] = useState(null);
  const [recoveryCodes, setRecoveryCodes] = useState(null);
  const [dialog, setDialog] = useState(null);

  function start() {
    startSetup.mutate(undefined, {
      onSuccess: (data) => {
        setSetup(data);
        startSetup.reset();
      },
      onError: (error) => {
        refreshAfterConflict(queryClient, error);

        if (error.code !== IDENTITY_CONFIRMATION_CANCELLED) {
          notify({ message: error.message, severity: 'error' });
        }
      },
    });
  }

  function openDialog(name) {
    regenerate.reset();
    disable.reset();
    setDialog(name);
  }

  function confirmRegenerate() {
    regenerate.mutate(undefined, {
      onSuccess: (codes) => {
        setDialog(null);
        setRecoveryCodes(codes);
        regenerate.reset();
      },
      onError: (error) => refreshAfterConflict(queryClient, error),
    });
  }

  function confirmDisable() {
    disable.mutate(undefined, {
      onSuccess: () => {
        setDialog(null);
        notify({ message: 'Doppia autenticazione disattivata' });
      },
      onError: (error) => refreshAfterConflict(queryClient, error),
    });
  }

  function renderContent() {
    if (setup) {
      return <TwoFactorSetup setup={setup} onClose={() => setSetup(null)} />;
    }

    if (recoveryCodes) {
      return (
        <Stack sx={{ gap: 3 }}>
          <Typography id="two-factor-card-title" variant="h5" component="h3">
            Nuovi codici di recupero
          </Typography>
          <RecoveryCodesPanel
            codes={recoveryCodes}
            onDone={() => {
              setRecoveryCodes(null);
              notify({ message: 'Codici di recupero aggiornati' });
            }}
          />
        </Stack>
      );
    }

    if (overview.two_factor_enabled) {
      return (
        <EnabledContent
          overview={overview}
          onRegenerate={() => openDialog('regenerate')}
          onDisable={() => openDialog('disable')}
        />
      );
    }

    return <DisabledContent onStart={start} starting={startSetup.isPending} />;
  }

  return (
    <Card component="section" aria-labelledby="two-factor-card-title">
      <CardContent>{renderContent()}</CardContent>
      {dialog === 'regenerate' ? (
        <ConfirmActionDialog
          title="Vuoi generare nuovi codici di recupero?"
          description="I codici che hai ora smetteranno di funzionare."
          keepLabel="Mantieni i codici attuali"
          confirmLabel="Genera nuovi codici"
          pending={regenerate.isPending}
          error={visibleActionError(regenerate)}
          onConfirm={confirmRegenerate}
          onClose={() => setDialog(null)}
        />
      ) : null}
      {dialog === 'disable' ? (
        <ConfirmActionDialog
          title="Vuoi disattivare la doppia autenticazione?"
          description="All'accesso ti chiederemo solo la password o una passkey. I codici di recupero smetteranno di funzionare e le altre sessioni verranno chiuse."
          keepLabel="Mantieni la doppia autenticazione"
          confirmLabel="Disattiva la doppia autenticazione"
          destructive
          pending={disable.isPending}
          error={visibleActionError(disable)}
          onConfirm={confirmDisable}
          onClose={() => setDialog(null)}
        />
      ) : null}
    </Card>
  );
}
