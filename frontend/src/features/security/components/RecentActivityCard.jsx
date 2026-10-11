import KeyOutlined from '@mui/icons-material/KeyOutlined';
import LockOutlined from '@mui/icons-material/LockOutlined';
import Login from '@mui/icons-material/Login';
import MailOutlined from '@mui/icons-material/MailOutlined';
import ShieldOutlined from '@mui/icons-material/ShieldOutlined';
import VerifiedUserOutlined from '@mui/icons-material/VerifiedUserOutlined';
import WarningAmberOutlined from '@mui/icons-material/WarningAmberOutlined';
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
import { Fragment } from 'react';
import { ErrorState } from '@/components/feedback/ErrorState';
import { useSecurityEvents } from '@/features/security/hooks/useSecurityEvents';
import { formatDateTime } from '@/utils/format';

const EVENT_ICONS = {
  login: Login,
  logout: Login,
  other_sessions_closed: Login,
  login_failed: WarningAmberOutlined,
  two_factor_challenge_failed: WarningAmberOutlined,
  password_changed: LockOutlined,
  password_reset: LockOutlined,
  passkey_added: KeyOutlined,
  passkey_removed: KeyOutlined,
  two_factor_enabled: VerifiedUserOutlined,
  two_factor_disabled: VerifiedUserOutlined,
  recovery_codes_regenerated: VerifiedUserOutlined,
  recovery_code_used: VerifiedUserOutlined,
  email_verified: MailOutlined,
  email_change_requested: MailOutlined,
  email_changed: MailOutlined,
  email_change_cancelled: MailOutlined,
};

function detailLine(event) {
  return [event.passkey_name, event.device, event.ip_address ? `IP ${event.ip_address}` : null]
    .filter(Boolean)
    .join(' · ');
}

function EventRow({ event }) {
  const Icon = EVENT_ICONS[event.type] ?? ShieldOutlined;

  return (
    <ListItem disableGutters sx={{ py: 1.5 }}>
      <Stack direction="row" sx={{ width: 1, gap: 2, alignItems: 'center' }}>
        <Avatar sx={{ bgcolor: 'background.media', color: 'text.primary' }}>
          <Icon fontSize="small" />
        </Avatar>
        <Stack
          direction={{ xs: 'column', md: 'row' }}
          sx={{ flexGrow: 1, minWidth: 0, gap: { xs: 0, md: 2 }, justifyContent: 'space-between' }}
        >
          <Stack sx={{ minWidth: 0 }}>
            <Typography variant="subtitle2" component="p">
              {event.label}
            </Typography>
            <Typography
              variant="caption"
              sx={{ color: 'text.secondary', overflowWrap: 'anywhere' }}
            >
              {detailLine(event)}
            </Typography>
          </Stack>
          <Typography
            variant="caption"
            component="time"
            dateTime={event.created_at}
            sx={{ color: 'text.secondary', flexShrink: 0 }}
          >
            {formatDateTime(event.created_at)}
          </Typography>
        </Stack>
      </Stack>
    </ListItem>
  );
}

function ActivityContent({ query }) {
  if (query.isPending) {
    return (
      <Stack sx={{ gap: 1 }} role="status" aria-label="Caricamento dell'attività recente">
        {[0, 1, 2].map((index) => (
          <Skeleton key={index} variant="rounded" sx={{ height: (theme) => theme.spacing(7) }} />
        ))}
      </Stack>
    );
  }

  if (query.isError) {
    return (
      <ErrorState
        message="Non è stato possibile caricare l'attività recente."
        onRetry={() => query.refetch()}
      />
    );
  }

  const events = query.data.pages.flatMap((page) => page.data);

  if (events.length === 0) {
    return (
      <Typography variant="body2" sx={{ color: 'text.secondary' }}>
        Non ci sono ancora attività da mostrare.
      </Typography>
    );
  }

  return (
    <Stack sx={{ gap: 1 }}>
      <List disablePadding aria-label="Attività recente">
        {events.map((event, index) => (
          <Fragment key={event.id}>
            {index > 0 ? <Divider component="li" /> : null}
            <EventRow event={event} />
          </Fragment>
        ))}
      </List>
      {query.hasNextPage ? (
        <Box>
          <Button
            onClick={() => query.fetchNextPage()}
            loading={query.isFetchingNextPage}
            sx={{ px: 1 }}
          >
            Mostra altre attività
          </Button>
        </Box>
      ) : null}
    </Stack>
  );
}

export function RecentActivityCard() {
  const eventsQuery = useSecurityEvents();

  return (
    <Card component="section" aria-labelledby="activity-card-title">
      <CardContent>
        <Stack sx={{ gap: 2 }}>
          <Typography id="activity-card-title" variant="h5" component="h3">
            Attività recente
          </Typography>
          <ActivityContent query={eventsQuery} />
        </Stack>
      </CardContent>
    </Card>
  );
}
