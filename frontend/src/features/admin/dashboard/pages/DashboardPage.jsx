import ChevronRight from '@mui/icons-material/ChevronRight';
import WarningAmberOutlined from '@mui/icons-material/WarningAmberOutlined';
import Grid from '@mui/material/Grid';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { paths } from '@/app/router/paths';
import { ErrorState } from '@/components/feedback/ErrorState';
import { LatestOrdersCard } from '@/features/admin/dashboard/components/LatestOrdersCard';
import { LowStockCard } from '@/features/admin/dashboard/components/LowStockCard';
import { OrdersByStatusCard } from '@/features/admin/dashboard/components/OrdersByStatusCard';
import { StatCard } from '@/features/admin/dashboard/components/StatCard';
import { useDashboard } from '@/features/admin/dashboard/hooks/useDashboard';
import { InlineAmountSkeleton } from '@/features/settings/components/InlineAmountSkeleton';
import { useAdminSettings } from '@/features/settings/hooks/useAdminSettings';
import { useIsDesktop } from '@/hooks/useIsDesktop';
import { formatMonthYear, formatPrice } from '@/utils/format';

const STAT_COLUMNS = { xs: 6, md: 3 };

function thresholdText(settingsQuery, suffix) {
  if (settingsQuery.isPending) {
    return <InlineAmountSkeleton />;
  }

  if (settingsQuery.isError) {
    return null;
  }

  return `${settingsQuery.data.low_stock_threshold} pezzi o meno${suffix}`;
}

function DashboardSkeleton() {
  return (
    <Stack sx={{ gap: 3 }} role="status" aria-label="Caricamento della dashboard">
      <Grid container spacing={{ xs: 2, md: 3 }}>
        {[0, 1, 2, 3].map((index) => (
          <Grid key={index} size={STAT_COLUMNS}>
            <Skeleton variant="rounded" sx={{ height: (theme) => theme.spacing(16) }} />
          </Grid>
        ))}
      </Grid>
      <Skeleton variant="rounded" sx={{ height: (theme) => theme.spacing(19) }} />
      <Grid container spacing={3}>
        <Grid size={{ xs: 12, md: 7 }}>
          <Skeleton variant="rounded" sx={{ height: (theme) => theme.spacing(48) }} />
        </Grid>
        <Grid size={{ xs: 12, md: 5 }}>
          <Skeleton variant="rounded" sx={{ height: (theme) => theme.spacing(48) }} />
        </Grid>
      </Grid>
    </Stack>
  );
}

function Dashboard({ dashboard, settingsQuery }) {
  const isDesktop = useIsDesktop();
  const monthLabel = formatMonthYear(dashboard.period.month);

  return (
    <Stack sx={{ gap: 3 }}>
      <Grid container spacing={{ xs: 2, md: 3 }}>
        <Grid size={STAT_COLUMNS}>
          <StatCard
            label="Fatturato del mese"
            value={
              <Typography variant={isDesktop ? 'priceLarge' : 'price'} component="p">
                {formatPrice(dashboard.revenue_month)}
              </Typography>
            }
            caption={
              isDesktop
                ? `${monthLabel} · solo ordini pagati, esclusi gli annullati`
                : 'Solo ordini pagati, esclusi gli annullati'
            }
          />
        </Grid>
        <Grid size={STAT_COLUMNS}>
          <StatCard
            label="Ordini del mese"
            value={
              <Typography variant="h4" component="p">
                {dashboard.orders_month}
              </Typography>
            }
            caption={isDesktop ? `${monthLabel} · tutti gli stati` : monthLabel}
          />
        </Grid>
        <Grid size={STAT_COLUMNS}>
          <StatCard
            label="Ordini da spedire"
            to={paths.adminOrdersFiltered({ status: 'paid' })}
            value={
              <Typography variant="h4" component="p">
                {dashboard.orders_to_ship}
              </Typography>
            }
            caption={
              <Stack component="span" direction="row" sx={{ alignItems: 'center', gap: 0.5 }}>
                {isDesktop ? 'Pagati, in attesa di spedizione' : 'Pagati, da spedire'}
                <ChevronRight fontSize="inherit" aria-hidden="true" />
              </Stack>
            }
          />
        </Grid>
        <Grid size={STAT_COLUMNS}>
          <StatCard
            label={isDesktop ? 'Prodotti con scorte basse' : 'Scorte basse'}
            value={
              <Stack direction="row" sx={{ alignItems: 'center', gap: 1, color: 'warning.main' }}>
                <Typography variant="h4" component="p" sx={{ color: 'inherit' }}>
                  {dashboard.low_stock_count}
                </Typography>
                <WarningAmberOutlined aria-hidden="true" />
              </Stack>
            }
            caption={thresholdText(settingsQuery, isDesktop ? ', esauriti compresi' : '')}
          />
        </Grid>
      </Grid>
      <OrdersByStatusCard
        ordersByStatus={dashboard.orders_by_status}
        month={dashboard.period.month}
      />
      <Grid container spacing={3} sx={{ alignItems: 'flex-start' }}>
        <Grid size={{ xs: 12, md: 7 }}>
          <LatestOrdersCard orders={dashboard.latest_orders} />
        </Grid>
        <Grid size={{ xs: 12, md: 5 }}>
          <LowStockCard
            products={dashboard.low_stock_products}
            thresholdText={thresholdText(settingsQuery, '')}
          />
        </Grid>
      </Grid>
    </Stack>
  );
}

export default function DashboardPage() {
  const dashboardQuery = useDashboard();
  const settingsQuery = useAdminSettings();

  if (dashboardQuery.isPending) {
    return <DashboardSkeleton />;
  }

  if (dashboardQuery.isError) {
    return (
      <ErrorState
        title="Non è stato possibile caricare la dashboard"
        message="Controlla la connessione e riprova."
        onRetry={() => dashboardQuery.refetch()}
      />
    );
  }

  return <Dashboard dashboard={dashboardQuery.data} settingsQuery={settingsQuery} />;
}
