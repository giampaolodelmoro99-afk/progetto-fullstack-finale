import ChatBubbleOutline from '@mui/icons-material/ChatBubbleOutlineOutlined';
import ExpandMore from '@mui/icons-material/ExpandMore';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Tooltip from '@mui/material/Tooltip';
import { useLocation } from 'react-router';
import { paths } from '@/app/router/paths';
import { useAssistant } from '@/features/assistant/hooks/useAssistant';
import { useAssistantStatus } from '@/features/assistant/hooks/useAssistantStatus';

const ROUTES_WITH_MOBILE_ACTION_BAR = [paths.cart, paths.checkout];

const UNAVAILABLE_TOOLTIP_ID = 'assistant-unavailable-tooltip';

function LauncherPosition({ children }) {
  return (
    <Box
      sx={(theme) => ({
        position: 'fixed',
        right: { xs: theme.spacing(2), md: theme.spacing(3) },
        bottom: { xs: theme.spacing(2), md: theme.spacing(3) },
        zIndex: 'fab',
      })}
    >
      {children}
    </Box>
  );
}

export function AssistantLauncher() {
  const { open, isDesktop, openPanel, closePanel, registerLauncher } = useAssistant();
  const { data, isPending } = useAssistantStatus();
  const { pathname } = useLocation();
  const available = data?.available === true;
  const expanded = open && isDesktop;

  if (!isDesktop && ROUTES_WITH_MOBILE_ACTION_BAR.includes(pathname)) {
    return null;
  }

  if (!available && !isPending) {
    return (
      <LauncherPosition>
        <Tooltip
          open
          title="Assistente non disponibile"
          placement="top-end"
          describeChild
          slotProps={{ popper: { sx: { zIndex: 'fab' } } }}
          id={UNAVAILABLE_TOOLTIP_ID}
        >
          <Box
            component="span"
            aria-describedby={UNAVAILABLE_TOOLTIP_ID}
            sx={{ display: 'inline-flex' }}
          >
            <Button
              variant="outlined"
              disabled
              startIcon={<ChatBubbleOutline />}
              sx={{ bgcolor: 'background.paper' }}
            >
              Chiedi all'assistente
            </Button>
          </Box>
        </Tooltip>
      </LauncherPosition>
    );
  }

  return (
    <LauncherPosition>
      <Button
        ref={registerLauncher}
        variant={expanded ? 'contained' : 'outlined'}
        disabled={!available}
        aria-expanded={open}
        aria-haspopup="dialog"
        startIcon={expanded ? <ExpandMore /> : <ChatBubbleOutline />}
        onClick={expanded ? closePanel : openPanel}
        sx={expanded ? undefined : { bgcolor: 'background.paper', boxShadow: 2 }}
      >
        {expanded ? "Chiudi l'assistente" : "Chiedi all'assistente"}
      </Button>
    </LauncherPosition>
  );
}
