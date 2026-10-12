import ChevronRight from '@mui/icons-material/ChevronRight';
import Box from '@mui/material/Box';
import CardActionArea from '@mui/material/CardActionArea';
import List from '@mui/material/List';
import ListItem from '@mui/material/ListItem';
import ListItemButton from '@mui/material/ListItemButton';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';
import { OrderStatusChip } from '@/components/data-display/OrderStatusChip';
import { AdminSectionCard } from '@/features/admin/components/AdminSectionCard';
import { useIsDesktop } from '@/hooks/useIsDesktop';
import { formatMonthYear, formatOrderCount } from '@/utils/format';

function linkProps(entry, month, monthLabel) {
  return {
    component: RouterLink,
    to: paths.adminOrdersFiltered({ status: entry.status.value, paid_month: month }),
    'aria-label': `${entry.status.label}: ${formatOrderCount(entry.count)} pagati a ${monthLabel}`,
  };
}

export function OrdersByStatusCard({ ordersByStatus, month }) {
  const isDesktop = useIsDesktop();
  const monthLabel = formatMonthYear(month);

  return (
    <AdminSectionCard
      title="Ordini per stato"
      aside={
        <Typography variant="body2" sx={{ color: 'text.secondary', flexGrow: 1 }}>
          {monthLabel}
        </Typography>
      }
    >
      {isDesktop ? (
        <Box
          component="ul"
          sx={{
            listStyle: 'none',
            m: 0,
            p: 0,
            display: 'grid',
            gridTemplateColumns: `repeat(${ordersByStatus.length}, 1fr)`,
            gap: 2,
          }}
        >
          {ordersByStatus.map((entry) => (
            <li key={entry.status.value}>
              <CardActionArea
                {...linkProps(entry, month, monthLabel)}
                sx={{ border: 1, borderColor: 'divider', borderRadius: 1, p: 2 }}
              >
                <Stack
                  direction="row"
                  sx={{ alignItems: 'center', justifyContent: 'space-between', gap: 1 }}
                >
                  <OrderStatusChip status={entry.status} />
                  <Typography variant="h4" component="span">
                    {entry.count}
                  </Typography>
                </Stack>
              </CardActionArea>
            </li>
          ))}
        </Box>
      ) : (
        <List disablePadding>
          {ordersByStatus.map((entry) => (
            <ListItem key={entry.status.value} disablePadding>
              <ListItemButton {...linkProps(entry, month, monthLabel)} sx={{ px: 0, gap: 1 }}>
                <Box sx={{ flexGrow: 1 }}>
                  <OrderStatusChip status={entry.status} />
                </Box>
                <Typography variant="subtitle1" component="span">
                  {entry.count}
                </Typography>
                <ChevronRight sx={{ color: 'text.secondary' }} />
              </ListItemButton>
            </ListItem>
          ))}
        </List>
      )}
    </AdminSectionCard>
  );
}
