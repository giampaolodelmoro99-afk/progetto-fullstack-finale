import Check from '@mui/icons-material/Check';
import Close from '@mui/icons-material/Close';
import Avatar from '@mui/material/Avatar';
import Box from '@mui/material/Box';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { formatDateTime } from '@/utils/format';

const PROGRESS_STEPS = [
  { status: 'paid', label: 'Pagato', hint: null },
  { status: 'shipped', label: 'Spedito', hint: 'Ti scriveremo quando parte' },
  { status: 'delivered', label: 'Consegnato', hint: 'Consegna in 24/48 ore dalla spedizione' },
];

const MARKER_SIZE = 3;

function buildSteps(order) {
  const rows = new Map(order.status_history.map((row) => [row.status.value, row]));

  if (order.status.value === 'cancelled') {
    return [...PROGRESS_STEPS.map((step) => step.status), 'cancelled']
      .filter((status) => rows.has(status))
      .map((status) => ({
        state: status === 'cancelled' ? 'cancelled' : 'done',
        row: rows.get(status),
      }));
  }

  return PROGRESS_STEPS.map((step) =>
    rows.has(step.status)
      ? { state: 'done', row: rows.get(step.status) }
      : { state: 'future', label: step.label, hint: step.hint },
  );
}

function Marker({ state }) {
  const size = (theme) => theme.spacing(MARKER_SIZE);

  if (state === 'future') {
    return (
      <Box
        sx={{
          width: size,
          height: size,
          flexShrink: 0,
          borderRadius: '50%',
          border: 1,
          borderColor: 'borderInput',
          bgcolor: 'background.paper',
        }}
      />
    );
  }

  const cancelled = state === 'cancelled';

  return (
    <Avatar
      sx={{
        width: size,
        height: size,
        bgcolor: cancelled ? 'error.light' : 'primary.main',
        color: cancelled ? 'error.main' : 'primary.contrastText',
      }}
    >
      {cancelled ? <Close sx={{ fontSize: 'inherit' }} /> : <Check sx={{ fontSize: 'inherit' }} />}
    </Avatar>
  );
}

function TimelineStep({ step, last }) {
  const label = step.row ? step.row.status.label : step.label;

  return (
    <Stack component="li" direction="row" sx={{ gap: 1.5 }}>
      <Stack sx={{ alignItems: 'center' }}>
        <Marker state={step.state} />
        {last ? null : (
          <Box
            sx={{
              flexGrow: 1,
              minHeight: (theme) => theme.spacing(2),
              borderLeft: 1,
              borderColor: 'divider',
            }}
          />
        )}
      </Stack>
      <Stack sx={{ gap: 0.25, pb: last ? 0 : 2 }}>
        <Typography variant="subtitle2" component="p">
          {label}
        </Typography>
        {step.row ? (
          <>
            <Typography variant="caption" sx={{ color: 'text.secondary' }}>
              {formatDateTime(step.row.created_at)}
            </Typography>
            <Typography variant="caption" sx={{ color: 'text.secondary' }}>
              {step.row.description}
            </Typography>
          </>
        ) : null}
        {!step.row && step.hint ? (
          <Typography variant="caption" sx={{ color: 'text.secondary' }}>
            {step.hint}
          </Typography>
        ) : null}
      </Stack>
    </Stack>
  );
}

export function OrderStatusTimeline({ order }) {
  const steps = buildSteps(order);

  return (
    <Stack component="ol" aria-label="Stato dell'ordine" sx={{ listStyle: 'none', m: 0, p: 0 }}>
      {steps.map((step, index) => (
        <TimelineStep
          key={step.row?.status.value ?? step.label}
          step={step}
          last={index === steps.length - 1}
        />
      ))}
    </Stack>
  );
}
