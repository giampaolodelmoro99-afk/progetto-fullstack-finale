import StopCircleOutlined from '@mui/icons-material/StopCircleOutlined';
import Button from '@mui/material/Button';
import { useAssistant } from '@/features/assistant/hooks/useAssistant';

export function AssistantStopButton() {
  const { stop, registerStop } = useAssistant();

  return (
    <Button
      ref={registerStop}
      variant="outlined"
      size="small"
      startIcon={<StopCircleOutlined />}
      onClick={stop}
      sx={{ alignSelf: 'flex-start', bgcolor: 'background.paper' }}
    >
      Interrompi
    </Button>
  );
}
