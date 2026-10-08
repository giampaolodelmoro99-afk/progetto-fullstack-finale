import Close from '@mui/icons-material/Close';
import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import IconButton from '@mui/material/IconButton';
import Snackbar from '@mui/material/Snackbar';
import { useCallback, useRef, useState } from 'react';
import { NotificationContext } from '@/hooks/useNotify';

const AUTO_HIDE_MILLISECONDS = 6000;

export function NotificationProvider({ children }) {
  const [notification, setNotification] = useState(null);
  const [open, setOpen] = useState(false);
  const sequence = useRef(0);

  const notify = useCallback(({ message, severity = 'success', action = null }) => {
    sequence.current += 1;
    setNotification({ id: sequence.current, message, severity, action });
    setOpen(true);
  }, []);

  function handleClose(event, reason) {
    if (reason === 'clickaway') {
      return;
    }

    setOpen(false);
  }

  function handleAction() {
    setOpen(false);
    notification.action.onClick();
  }

  return (
    <NotificationContext.Provider value={notify}>
      {children}
      <Snackbar
        key={notification?.id}
        open={open}
        autoHideDuration={AUTO_HIDE_MILLISECONDS}
        onClose={handleClose}
        anchorOrigin={{ vertical: 'bottom', horizontal: 'center' }}
      >
        {notification ? (
          <Alert
            severity={notification.severity}
            variant="filled"
            onClose={notification.action ? undefined : handleClose}
            action={
              notification.action ? (
                <>
                  <Button color="inherit" size="small" onClick={handleAction}>
                    {notification.action.label}
                  </Button>
                  <IconButton
                    color="inherit"
                    size="small"
                    aria-label="Chiudi"
                    onClick={handleClose}
                  >
                    <Close fontSize="small" />
                  </IconButton>
                </>
              ) : undefined
            }
            sx={{ width: 1 }}
          >
            {notification.message}
          </Alert>
        ) : undefined}
      </Snackbar>
    </NotificationContext.Provider>
  );
}
