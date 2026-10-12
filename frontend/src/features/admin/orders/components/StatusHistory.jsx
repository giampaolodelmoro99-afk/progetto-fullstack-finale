import Check from '@mui/icons-material/Check';
import Close from '@mui/icons-material/Close';
import Avatar from '@mui/material/Avatar';
import Box from '@mui/material/Box';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { AdminSectionCard } from '@/features/admin/components/AdminSectionCard';
import { formatDateTimeShort } from '@/utils/format';

const MARKER_SIZE = 3;

function Marker({ cancelled }) {
  const size = (theme) => theme.spacing(MARKER_SIZE);

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

export function StatusHistory({ history }) {
  return (
    <AdminSectionCard title="Cronologia degli stati">
      <Stack
        component="ol"
        aria-label="Cronologia degli stati"
        sx={{ listStyle: 'none', m: 0, p: 0 }}
      >
        {history.map((row, index) => {
          const last = index === history.length - 1;
          const cancelled = row.status.value === 'cancelled';

          return (
            <Stack
              key={`${row.status.value}-${row.created_at}`}
              component="li"
              direction="row"
              sx={{ gap: 1.5 }}
            >
              <Stack sx={{ alignItems: 'center' }}>
                <Marker cancelled={cancelled} />
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
              <Stack
                direction={{ xs: 'column', md: 'row' }}
                sx={{ flexGrow: 1, justifyContent: 'space-between', gap: 0.5, pb: last ? 0 : 2 }}
              >
                <Stack sx={{ gap: 0.25 }}>
                  <Typography
                    variant="subtitle2"
                    component="p"
                    sx={{ color: cancelled ? 'error.main' : 'text.primary' }}
                  >
                    {row.status.label}
                  </Typography>
                  <Typography variant="caption" sx={{ color: 'text.secondary' }}>
                    {row.description}
                  </Typography>
                </Stack>
                <Typography variant="caption" sx={{ color: 'text.secondary', flexShrink: 0 }}>
                  {formatDateTimeShort(row.created_at)}
                </Typography>
              </Stack>
            </Stack>
          );
        })}
      </Stack>
    </AdminSectionCard>
  );
}
