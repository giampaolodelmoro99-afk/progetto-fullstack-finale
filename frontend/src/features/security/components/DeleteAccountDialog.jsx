import ArrowForward from '@mui/icons-material/ArrowForward';
import KeyOutlined from '@mui/icons-material/KeyOutlined';
import PersonOutlineOutlined from '@mui/icons-material/PersonOutlineOutlined';
import PlaceOutlined from '@mui/icons-material/PlaceOutlined';
import ShoppingBagOutlined from '@mui/icons-material/ShoppingBagOutlined';
import Alert from '@mui/material/Alert';
import AlertTitle from '@mui/material/AlertTitle';
import Button from '@mui/material/Button';
import Checkbox from '@mui/material/Checkbox';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogTitle from '@mui/material/DialogTitle';
import FormControlLabel from '@mui/material/FormControlLabel';
import List from '@mui/material/List';
import ListItem from '@mui/material/ListItem';
import ListItemIcon from '@mui/material/ListItemIcon';
import ListItemText from '@mui/material/ListItemText';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useId, useState } from 'react';
import { Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';
import { useDeleteAccount } from '@/features/security/hooks/useDeleteAccount';
import { IDENTITY_CONFIRMATION_CANCELLED } from '@/utils/normalizeApiError';

const OPEN_ORDERS = 'account_has_open_orders';

const DELETED_DATA = [
  { icon: PersonOutlineOutlined, label: 'I tuoi dati personali' },
  { icon: PlaceOutlined, label: 'Gli indirizzi salvati' },
  { icon: ShoppingBagOutlined, label: 'Il carrello' },
  { icon: KeyOutlined, label: "L'accesso: password, passkey e doppia autenticazione" },
];

export function DeleteAccountDialog({ onClose }) {
  const titleId = useId();
  const descriptionId = useId();
  const deleteAccount = useDeleteAccount();
  const [understood, setUnderstood] = useState(false);
  const [blockedMessage, setBlockedMessage] = useState(null);
  const [error, setError] = useState(null);

  function close() {
    if (!deleteAccount.isPending) {
      onClose();
    }
  }

  function confirm() {
    setError(null);
    deleteAccount.mutate(undefined, {
      onError: (failure) => {
        if (failure.code === IDENTITY_CONFIRMATION_CANCELLED) {
          return;
        }

        if (failure.code === OPEN_ORDERS) {
          setBlockedMessage(failure.message);
          return;
        }

        setError(failure.message);
      },
    });
  }

  if (blockedMessage) {
    return (
      <Dialog
        open
        onClose={close}
        aria-labelledby={titleId}
        aria-describedby={descriptionId}
        fullWidth
        maxWidth="sm"
      >
        <DialogTitle id={titleId}>Per ora non puoi eliminare l&apos;account</DialogTitle>
        <DialogContent>
          <Alert id={descriptionId} severity="warning">
            {blockedMessage}
          </Alert>
        </DialogContent>
        <DialogActions>
          <Button onClick={close}>Chiudi</Button>
          <Button
            variant="contained"
            component={RouterLink}
            to={paths.accountOrders}
            endIcon={<ArrowForward />}
          >
            I miei ordini
          </Button>
        </DialogActions>
      </Dialog>
    );
  }

  return (
    <Dialog
      open
      onClose={close}
      aria-labelledby={titleId}
      aria-describedby={descriptionId}
      fullWidth
      maxWidth="sm"
    >
      <DialogTitle id={titleId}>Vuoi eliminare il tuo account?</DialogTitle>
      <DialogContent>
        <Stack sx={{ gap: 2 }}>
          {error ? <Alert severity="error">{error}</Alert> : null}
          <Stack sx={{ gap: 0.5 }}>
            <Typography variant="subtitle2" component="h3">
              Cosa viene cancellato
            </Typography>
            <List id={descriptionId} dense disablePadding>
              {DELETED_DATA.map((item) => (
                <ListItem key={item.label} disableGutters>
                  <ListItemIcon>
                    <item.icon fontSize="small" />
                  </ListItemIcon>
                  <ListItemText primary={item.label} />
                </ListItem>
              ))}
            </List>
          </Stack>
          <Stack sx={{ gap: 1 }}>
            <Typography variant="subtitle2" component="h3">
              Cosa conserviamo
            </Typography>
            <Alert severity="info">
              <AlertTitle>Gli ordini, per 10 anni</AlertTitle>È un obbligo di legge: i documenti di
              vendita vanno conservati.
            </Alert>
          </Stack>
          <FormControlLabel
            control={
              <Checkbox
                checked={understood}
                onChange={(event) => setUnderstood(event.target.checked)}
              />
            }
            label="Ho capito che l'operazione non si può annullare"
          />
        </Stack>
      </DialogContent>
      <DialogActions>
        <Button onClick={close} disabled={deleteAccount.isPending}>
          Annulla
        </Button>
        <Button
          variant="contained"
          color="error"
          onClick={confirm}
          disabled={!understood}
          loading={deleteAccount.isPending}
        >
          Elimina l&apos;account
        </Button>
      </DialogActions>
    </Dialog>
  );
}
