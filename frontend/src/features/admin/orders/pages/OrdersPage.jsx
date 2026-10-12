import Close from '@mui/icons-material/Close';
import LocalMallOutlined from '@mui/icons-material/LocalMallOutlined';
import Box from '@mui/material/Box';
import Card from '@mui/material/Card';
import Chip from '@mui/material/Chip';
import FormControlLabel from '@mui/material/FormControlLabel';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';
import Switch from '@mui/material/Switch';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableContainer from '@mui/material/TableContainer';
import TableRow from '@mui/material/TableRow';
import Typography from '@mui/material/Typography';
import { useMemo } from 'react';
import { Navigate } from 'react-router';
import { ErrorState } from '@/components/feedback/ErrorState';
import { AdminEmptyState } from '@/features/admin/components/AdminEmptyState';
import { AdminTableHead } from '@/features/admin/components/AdminTableHead';
import { AdminTableSkeleton } from '@/features/admin/components/AdminTableSkeleton';
import { FilterSelect } from '@/features/admin/components/FilterSelect';
import { ListPagination } from '@/features/admin/components/ListPagination';
import { SearchField } from '@/features/admin/components/SearchField';
import { useListFilters } from '@/features/admin/hooks/useListFilters';
import { toApiParams } from '@/features/admin/listParams';
import { OrderCards } from '@/features/admin/orders/components/OrderCards';
import { OrderRows } from '@/features/admin/orders/components/OrderRows';
import { useAdminOrders } from '@/features/admin/orders/hooks/useAdminOrders';
import { ORDER_COLUMNS } from '@/features/admin/orders/orderColumns';
import { parseOrderFilters, serializeOrderFilters } from '@/features/admin/orders/orderFilters';
import { useAdminSettings } from '@/features/settings/hooks/useAdminSettings';
import { useIsDesktop } from '@/hooks/useIsDesktop';
import { formatMonthYear, formatOrderCount } from '@/utils/format';

function emptyDescription(search) {
  return search
    ? `Nessun ordine corrisponde a «${search}». Controlla il numero d'ordine o l'email, oppure azzera i filtri.`
    : 'Nessun ordine corrisponde ai filtri.';
}

function statusOptions(settingsQuery, status) {
  if (settingsQuery.data) {
    return settingsQuery.data.order_statuses;
  }

  return status ? [{ value: status, label: '' }] : [];
}

export default function OrdersPage() {
  const { filters, update, reset } = useListFilters(parseOrderFilters, serializeOrderFilters);
  const apiParams = useMemo(() => toApiParams(serializeOrderFilters(filters)), [filters]);
  const ordersQuery = useAdminOrders(apiParams);
  const settingsQuery = useAdminSettings();
  const isDesktop = useIsDesktop();
  const orders = ordersQuery.data?.data ?? [];
  const meta = ordersQuery.data?.meta;
  const isEmpty = ordersQuery.isSuccess && meta.total === 0;

  if (
    ordersQuery.isSuccess &&
    !ordersQuery.isPlaceholderData &&
    meta.total > 0 &&
    filters.page > meta.last_page
  ) {
    return (
      <Navigate
        to={{ search: serializeOrderFilters({ ...filters, page: meta.last_page }).toString() }}
        replace
      />
    );
  }

  const emptyState = (
    <AdminEmptyState
      icon={<LocalMallOutlined />}
      title="Nessun ordine trovato"
      description={emptyDescription(filters.search)}
      onReset={reset}
    />
  );

  const pagination =
    meta && meta.total > 0 ? (
      <ListPagination
        meta={meta}
        page={filters.page}
        perPage={filters.perPage}
        onPageChange={(page) => update({ page })}
        onPerPageChange={(perPage) => update({ perPage })}
      />
    ) : null;

  const statusFilter = (
    <FilterSelect
      label="Stato"
      value={filters.status}
      allLabel="Tutti"
      options={statusOptions(settingsQuery, filters.status)}
      onChange={(status) => update({ status })}
      sx={{ minWidth: { md: (theme) => theme.layout.adminFilterMinWidth } }}
    />
  );

  const searchFilter = (
    <SearchField
      label="Cerca per numero d'ordine o email"
      placeholder="Es. ORD-2026-000042"
      value={filters.search}
      onSearch={(search) => update({ search })}
      sx={{
        flexGrow: { md: 1 },
        maxWidth: { md: (theme) => theme.layout.adminOrderSearchMaxWidth },
      }}
    />
  );

  return (
    <Stack sx={{ gap: 3 }}>
      <Stack direction={{ xs: 'column', md: 'row' }} sx={{ gap: 2, alignItems: { md: 'center' } }}>
        {isDesktop ? statusFilter : searchFilter}
        {isDesktop ? searchFilter : statusFilter}
        <FormControlLabel
          label="Solo pagamenti non riusciti"
          labelPlacement={isDesktop ? 'end' : 'start'}
          sx={{ mx: 0, justifyContent: { xs: 'space-between', md: 'flex-start' }, gap: 1 }}
          control={
            <Switch
              checked={filters.failedPayment}
              onChange={(event) => update({ failedPayment: event.target.checked })}
            />
          }
        />
        <Box sx={{ flexGrow: 1, display: { xs: 'none', md: 'block' } }} />
        <Typography variant="body2" role="status" sx={{ color: 'text.secondary' }}>
          {meta ? formatOrderCount(meta.total) : ''}
        </Typography>
      </Stack>
      {filters.paidMonth ? (
        <Box>
          <Chip
            variant="outlined"
            label={`Pagati a ${formatMonthYear(filters.paidMonth)}`}
            onDelete={() => update({ paidMonth: '' })}
            deleteIcon={<Close titleAccess="Rimuovi il filtro del mese" />}
          />
        </Box>
      ) : null}
      {ordersQuery.isError ? (
        <ErrorState
          title="Non è stato possibile caricare gli ordini"
          message="Controlla la connessione e riprova."
          onRetry={() => ordersQuery.refetch()}
        />
      ) : null}
      {isDesktop && !ordersQuery.isError ? (
        <Card>
          <TableContainer
            aria-busy={ordersQuery.isPlaceholderData}
            sx={{
              opacity: ordersQuery.isPlaceholderData ? 0.6 : 1,
              transition: (theme) => theme.transitions.create('opacity'),
            }}
          >
            <Table aria-label="Ordini">
              <AdminTableHead columns={ORDER_COLUMNS} />
              {ordersQuery.isPending ? <AdminTableSkeleton columns={ORDER_COLUMNS} /> : null}
              {isEmpty ? (
                <TableBody>
                  <TableRow>
                    <TableCell colSpan={ORDER_COLUMNS.length} sx={{ borderBottom: 0 }}>
                      {emptyState}
                    </TableCell>
                  </TableRow>
                </TableBody>
              ) : null}
              {orders.length > 0 ? <OrderRows orders={orders} /> : null}
            </Table>
          </TableContainer>
          {pagination}
        </Card>
      ) : null}
      {!isDesktop && ordersQuery.isPending ? (
        <Stack sx={{ gap: 1.5 }} role="status" aria-label="Caricamento degli ordini">
          {[0, 1, 2, 3].map((index) => (
            <Skeleton key={index} variant="rounded" sx={{ height: (theme) => theme.spacing(13) }} />
          ))}
        </Stack>
      ) : null}
      {!isDesktop && isEmpty ? <Card>{emptyState}</Card> : null}
      {!isDesktop && orders.length > 0 ? (
        <>
          <OrderCards orders={orders} />
          {pagination}
        </>
      ) : null}
    </Stack>
  );
}
