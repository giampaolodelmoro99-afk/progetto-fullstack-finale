import Add from '@mui/icons-material/Add';
import InfoOutlined from '@mui/icons-material/InfoOutlined';
import KeyOutlined from '@mui/icons-material/KeyOutlined';
import Avatar from '@mui/material/Avatar';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Divider from '@mui/material/Divider';
import List from '@mui/material/List';
import ListItem from '@mui/material/ListItem';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { Fragment, useState } from 'react';
import { ErrorState } from '@/components/feedback/ErrorState';
import { ConfirmActionDialog } from '@/features/security/components/ConfirmActionDialog';
import { PasskeyNameDialog } from '@/features/security/components/PasskeyNameDialog';
import { useAddPasskey } from '@/features/security/hooks/useAddPasskey';
import { usePasskeys } from '@/features/security/hooks/usePasskeys';
import { usePasskeySupport } from '@/features/security/hooks/usePasskeySupport';
import { useRemovePasskey } from '@/features/security/hooks/useRemovePasskey';
import { useRenamePasskey } from '@/features/security/hooks/useRenamePasskey';
import { visibleActionError } from '@/features/security/utils/actionError';
import { isPasskeyCancelled } from '@/features/security/utils/authenticateWithPasskey';
import { useNotify } from '@/hooks/useNotify';
import { formatDate } from '@/utils/format';
import { IDENTITY_CONFIRMATION_CANCELLED } from '@/utils/normalizeApiError';

function usageLine(passkey) {
  const created = `Creata il ${formatDate(passkey.created_at)}`;

  return passkey.last_used_at
    ? `${created} · Ultimo utilizzo ${formatDate(passkey.last_used_at)}`
    : `${created} · Mai usata`;
}

function PasskeyRow({ passkey, onRename, onRemove }) {
  return (
    <ListItem disableGutters sx={{ py: 2 }}>
      <Stack
        direction={{ xs: 'column', sm: 'row' }}
        sx={{ width: 1, gap: 1.5, justifyContent: 'space-between', alignItems: { sm: 'center' } }}
      >
        <Stack direction="row" sx={{ gap: 2, alignItems: 'center', minWidth: 0 }}>
          <Avatar sx={{ bgcolor: 'background.media', color: 'text.primary' }}>
            <KeyOutlined />
          </Avatar>
          <Stack sx={{ minWidth: 0 }}>
            <Typography variant="subtitle2" component="p" sx={{ overflowWrap: 'anywhere' }}>
              {passkey.name}
            </Typography>
            <Typography variant="caption" sx={{ color: 'text.secondary' }}>
              {usageLine(passkey)}
            </Typography>
          </Stack>
        </Stack>
        <Stack direction="row" sx={{ gap: 1, flexShrink: 0 }}>
          <Button size="small" onClick={onRename} aria-label={`Rinomina ${passkey.name}`}>
            Rinomina
          </Button>
          <Button
            size="small"
            color="error"
            onClick={onRemove}
            aria-label={`Rimuovi ${passkey.name}`}
          >
            Rimuovi
          </Button>
        </Stack>
      </Stack>
    </ListItem>
  );
}

function PasskeyList({ query, onRename, onRemove }) {
  if (query.isPending) {
    return (
      <Stack sx={{ gap: 1 }} role="status" aria-label="Caricamento delle passkey">
        <Skeleton variant="rounded" sx={{ height: (theme) => theme.spacing(8) }} />
        <Skeleton variant="rounded" sx={{ height: (theme) => theme.spacing(8) }} />
      </Stack>
    );
  }

  if (query.isError) {
    return (
      <ErrorState
        message="Non è stato possibile caricare le passkey."
        onRetry={() => query.refetch()}
      />
    );
  }

  if (query.data.length === 0) {
    return (
      <Typography variant="body2" sx={{ color: 'text.secondary' }}>
        Non hai ancora aggiunto passkey.
      </Typography>
    );
  }

  return (
    <List disablePadding aria-label="Le tue passkey">
      {query.data.map((passkey, index) => (
        <Fragment key={passkey.id}>
          {index > 0 ? <Divider component="li" /> : null}
          <PasskeyRow
            passkey={passkey}
            onRename={() => onRename(passkey)}
            onRemove={() => onRemove(passkey)}
          />
        </Fragment>
      ))}
    </List>
  );
}

export function PasskeysCard({ twoFactorEnabled }) {
  const passkeySupported = usePasskeySupport();
  const passkeysQuery = usePasskeys();
  const addPasskey = useAddPasskey();
  const renamePasskey = useRenamePasskey();
  const removePasskey = useRemovePasskey();
  const notify = useNotify();
  const [dialog, setDialog] = useState(null);
  const [dialogError, setDialogError] = useState(null);

  function openDialog(next) {
    setDialogError(null);
    removePasskey.reset();
    setDialog(next);
  }

  function handleNameError(error, setNameError) {
    if (isPasskeyCancelled(error) || error.code === IDENTITY_CONFIRMATION_CANCELLED) {
      return;
    }

    if (error.fieldErrors?.name) {
      setNameError(error.fieldErrors.name);
      return;
    }

    setDialogError(error.fieldErrors?.credential ?? error.message);
  }

  function submitNew(name, setNameError) {
    setDialogError(null);
    addPasskey.mutate(name, {
      onSuccess: () => {
        setDialog(null);
        notify({ message: 'Passkey aggiunta' });
      },
      onError: (error) => handleNameError(error, setNameError),
    });
  }

  function submitRename(name, setNameError) {
    setDialogError(null);
    renamePasskey.mutate(
      { id: dialog.passkey.id, name },
      {
        onSuccess: () => {
          setDialog(null);
          notify({ message: 'Passkey rinominata' });
        },
        onError: (error) => handleNameError(error, setNameError),
      },
    );
  }

  function confirmRemove() {
    removePasskey.mutate(dialog.passkey, {
      onSuccess: () => {
        setDialog(null);
        notify({ message: 'Passkey rimossa' });
      },
    });
  }

  return (
    <Card component="section" aria-labelledby="passkeys-card-title">
      <CardContent>
        <Stack sx={{ gap: 2 }}>
          <Typography id="passkeys-card-title" variant="h5" component="h3">
            Passkey
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Con una passkey accedi con l&apos;impronta, il volto o il PIN del dispositivo, senza
            digitare la password.{twoFactorEnabled ? ' Vale anche come secondo fattore.' : ''}
          </Typography>
          <PasskeyList
            query={passkeysQuery}
            onRename={(passkey) => openDialog({ type: 'rename', passkey })}
            onRemove={(passkey) => openDialog({ type: 'remove', passkey })}
          />
          <Box>
            {passkeySupported ? (
              <Button
                variant="outlined"
                startIcon={<Add />}
                onClick={() => openDialog({ type: 'add' })}
                sx={{ width: { xs: 1, sm: 'auto' } }}
              >
                Aggiungi una passkey
              </Button>
            ) : (
              <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                Questo browser non supporta le passkey: per aggiungerne una usa un browser
                aggiornato.
              </Typography>
            )}
          </Box>
          <Stack direction="row" sx={{ gap: 1, alignItems: 'flex-start' }}>
            <InfoOutlined fontSize="small" sx={{ color: 'text.secondary' }} />
            <Typography variant="caption" sx={{ color: 'text.secondary' }}>
              Ti consigliamo di registrarne almeno due, su dispositivi diversi: se ne perdi uno
              potrai comunque accedere.
            </Typography>
          </Stack>
        </Stack>
      </CardContent>
      {dialog?.type === 'add' ? (
        <PasskeyNameDialog
          title="Aggiungi una passkey"
          description="Dai un nome alla passkey, poi il dispositivo ti chiederà l'impronta, il volto o il PIN."
          submitLabel="Continua"
          pending={addPasskey.isPending}
          error={dialogError}
          onSubmit={submitNew}
          onClose={() => setDialog(null)}
        />
      ) : null}
      {dialog?.type === 'rename' ? (
        <PasskeyNameDialog
          title="Rinomina la passkey"
          initialName={dialog.passkey.name}
          submitLabel="Salva"
          pending={renamePasskey.isPending}
          error={dialogError}
          onSubmit={submitRename}
          onClose={() => setDialog(null)}
        />
      ) : null}
      {dialog?.type === 'remove' ? (
        <ConfirmActionDialog
          title={`Vuoi rimuovere la passkey «${dialog.passkey.name}»?`}
          description="Non potrai più usarla per accedere."
          keepLabel="Mantieni la passkey"
          confirmLabel="Rimuovi la passkey"
          destructive
          pending={removePasskey.isPending}
          error={visibleActionError(removePasskey)}
          onConfirm={confirmRemove}
          onClose={() => setDialog(null)}
        />
      ) : null}
    </Card>
  );
}
