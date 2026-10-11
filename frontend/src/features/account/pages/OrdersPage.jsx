import ShoppingBagOutlined from '@mui/icons-material/ShoppingBagOutlined';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import Pagination from '@mui/material/Pagination';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';
import { Navigate, Link as RouterLink, useSearchParams } from 'react-router';
import { paths } from '@/app/router/paths';
import { EmptyState } from '@/components/feedback/EmptyState';
import { ErrorState } from '@/components/feedback/ErrorState';
import { AccountPageHeader } from '@/features/account/components/AccountPageHeader';
import { OrderCardList } from '@/features/account/components/OrderCardList';
import { OrdersTable } from '@/features/account/components/OrdersTable';
import { useOrders } from '@/features/account/hooks/useOrders';
import { useIsDesktop } from '@/hooks/useIsDesktop';

function readPage(searchParams) {
  const page = Number(searchParams.get('page'));

  return Number.isInteger(page) && page > 1 ? page : 1;
}

function OrdersSkeleton() {
  return (
    <Stack sx={{ gap: 1.5 }} role="status" aria-label="Caricamento degli ordini">
      {[0, 1, 2].map((index) => (
        <Skeleton key={index} variant="rounded" sx={{ height: (theme) => theme.spacing(9) }} />
      ))}
    </Stack>
  );
}

export default function OrdersPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const page = readPage(searchParams);
  const ordersQuery = useOrders(page);
  const isDesktop = useIsDesktop();
  const orders = ordersQuery.data?.data ?? [];
  const lastPage = ordersQuery.data?.meta.last_page ?? 1;
  const total = ordersQuery.data?.meta.total ?? 0;
  const beyondLastPage =
    ordersQuery.isSuccess && !ordersQuery.isPlaceholderData && total > 0 && page > lastPage;

  function changePage(event, value) {
    setSearchParams(value > 1 ? { page: String(value) } : {});
    window.scrollTo({ top: 0 });
  }

  if (beyondLastPage) {
    return <Navigate to={{ search: lastPage > 1 ? `?page=${lastPage}` : '' }} replace />;
  }

  return (
    <Stack sx={{ gap: 3 }}>
      <AccountPageHeader
        title="I miei ordini"
        description="Qui trovi lo stato e i dettagli di ogni ordine."
      />
      {ordersQuery.isPending ? <OrdersSkeleton /> : null}
      {ordersQuery.isError ? (
        <ErrorState
          title="Non è stato possibile caricare i tuoi ordini"
          message="Controlla la connessione e riprova."
          onRetry={() => ordersQuery.refetch()}
        />
      ) : null}
      {ordersQuery.isSuccess && total === 0 ? (
        <Card>
          <EmptyState
            icon={<ShoppingBagOutlined />}
            title="Non hai ancora fatto ordini"
            titleComponent="h3"
            description="Quando acquisterai qualcosa, qui troverai lo stato e i dettagli di ogni ordine."
            action={
              <Button component={RouterLink} to={paths.products} variant="contained">
                Scopri la collezione
              </Button>
            }
          />
        </Card>
      ) : null}
      {orders.length > 0 ? (
        <>
          <Box
            aria-busy={ordersQuery.isPlaceholderData}
            sx={{
              opacity: ordersQuery.isPlaceholderData ? 0.6 : 1,
              transition: (theme) => theme.transitions.create('opacity'),
            }}
          >
            {isDesktop ? <OrdersTable orders={orders} /> : <OrderCardList orders={orders} />}
          </Box>
          {lastPage > 1 ? (
            <Pagination
              count={lastPage}
              page={page}
              onChange={changePage}
              shape="rounded"
              siblingCount={isDesktop ? 1 : 0}
              sx={{ alignSelf: 'center' }}
            />
          ) : null}
        </>
      ) : null}
    </Stack>
  );
}
