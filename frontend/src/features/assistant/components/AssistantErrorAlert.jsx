import Add from '@mui/icons-material/Add';
import ErrorOutline from '@mui/icons-material/ErrorOutlineOutlined';
import Refresh from '@mui/icons-material/Refresh';
import ScheduleOutlined from '@mui/icons-material/ScheduleOutlined';
import WarningAmberOutlined from '@mui/icons-material/WarningAmberOutlined';
import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import { useAssistant } from '@/features/assistant/hooks/useAssistant';

const APPEARANCE = {
  busy: { severity: 'warning', Icon: WarningAmberOutlined },
  rate_limited: { severity: 'warning', Icon: ScheduleOutlined },
};

const DEFAULT_APPEARANCE = { severity: 'error', Icon: ErrorOutline };

export function AssistantErrorAlert() {
  const { error, isBlocking, retry, reset, registerErrorAction, registerAlert } = useAssistant();
  const { severity, Icon } = APPEARANCE[error.kind] ?? DEFAULT_APPEARANCE;
  const action =
    error.kind === 'invalid' ? (
      <Button
        ref={registerErrorAction}
        color="inherit"
        size="small"
        startIcon={<Add />}
        onClick={reset}
      >
        Nuova conversazione
      </Button>
    ) : (
      <Button
        ref={registerErrorAction}
        color="inherit"
        size="small"
        startIcon={<Refresh />}
        disabled={isBlocking}
        onClick={retry}
      >
        Riprova
      </Button>
    );

  return (
    <Alert
      ref={registerAlert}
      tabIndex={-1}
      role="note"
      severity={severity}
      icon={<Icon fontSize="inherit" />}
      action={action}
      sx={{ alignItems: 'center' }}
    >
      {error.message}
    </Alert>
  );
}
