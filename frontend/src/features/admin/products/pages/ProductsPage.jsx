import Add from '@mui/icons-material/Add';
import SearchOutlined from '@mui/icons-material/SearchOutlined';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Divider from '@mui/material/Divider';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableContainer from '@mui/material/TableContainer';
import TableRow from '@mui/material/TableRow';
import Typography from '@mui/material/Typography';
import { useMemo, useState } from 'react';
import { Navigate, Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';
import { ProductThumbnail } from '@/components/data-display/ProductThumbnail';
import { ErrorState } from '@/components/feedback/ErrorState';
import { useAdminCategories } from '@/features/admin/categories/hooks/useAdminCategories';
import { AdminEmptyState } from '@/features/admin/components/AdminEmptyState';
import { AdminTableHead } from '@/features/admin/components/AdminTableHead';
import { AdminTableSkeleton } from '@/features/admin/components/AdminTableSkeleton';
import { FilterSelect } from '@/features/admin/components/FilterSelect';
import { ListPagination } from '@/features/admin/components/ListPagination';
import { SearchField } from '@/features/admin/components/SearchField';
import { useListFilters } from '@/features/admin/hooks/useListFilters';
import { toApiParams } from '@/features/admin/listParams';
import { DeleteProductDialog } from '@/features/admin/products/components/DeleteProductDialog';
import { ProductActions } from '@/features/admin/products/components/ProductActions';
import { ProductStatusChip } from '@/features/admin/products/components/ProductStatusChip';
import { ProductStock } from '@/features/admin/products/components/ProductStock';
import { useAdminProducts } from '@/features/admin/products/hooks/useAdminProducts';
import {
  parseProductFilters,
  productStatusOptions,
  serializeProductFilters,
} from '@/features/admin/products/productFilters';
import { useIsDesktop } from '@/hooks/useIsDesktop';
import { formatPrice } from '@/utils/format';

const COLUMNS = [
  { key: 'image', label: 'Immagine', hidden: true, skeleton: 'thumbnail' },
  { key: 'name', label: 'Nome' },
  { key: 'sku', label: 'SKU' },
  { key: 'category', label: 'Categoria' },
  { key: 'price', label: 'Prezzo', align: 'right' },
  { key: 'stock', label: 'Stock', align: 'right' },
  { key: 'status', label: 'Stato', skeleton: 'chip' },
  { key: 'actions', label: 'Azioni', align: 'right', skeleton: 'actions' },
];

function emptyDescription(search) {
  return search
    ? `Nessun prodotto corrisponde a «${search}». Controlla il nome o lo SKU, oppure azzera i filtri.`
    : 'Nessun prodotto corrisponde ai filtri.';
}

function ProductRows({ products, onDelete }) {
  return (
    <TableBody>
      {products.map((product) => (
        <TableRow key={product.id}>
          <TableCell>
            <ProductThumbnail cover={product.cover} name={product.name} />
          </TableCell>
          <TableCell>
            <Typography variant="subtitle2" component="span">
              {product.name}
            </Typography>
          </TableCell>
          <TableCell sx={{ color: 'text.secondary' }}>{product.sku}</TableCell>
          <TableCell>{product.category?.name}</TableCell>
          <TableCell align="right">
            <Typography variant="subtitle2" component="span">
              {formatPrice(product.price)}
            </Typography>
          </TableCell>
          <TableCell align="right">
            <ProductStock product={product} />
          </TableCell>
          <TableCell>
            <ProductStatusChip isActive={product.is_active} />
          </TableCell>
          <TableCell align="right">
            <ProductActions product={product} onDelete={onDelete} />
          </TableCell>
        </TableRow>
      ))}
    </TableBody>
  );
}

function ProductCards({ products, onDelete }) {
  return (
    <Stack component="ul" aria-label="Prodotti" sx={{ listStyle: 'none', m: 0, p: 0, gap: 1.5 }}>
      {products.map((product) => (
        <li key={product.id}>
          <Card>
            <CardContent>
              <Stack direction="row" sx={{ gap: 1.5, alignItems: 'flex-start' }}>
                <ProductThumbnail cover={product.cover} name={product.name} />
                <Stack sx={{ flexGrow: 1, minWidth: 0, gap: 0.5 }}>
                  <Stack
                    direction="row"
                    sx={{ gap: 1, justifyContent: 'space-between', alignItems: 'center' }}
                  >
                    <Typography variant="subtitle2" component="h3">
                      {product.name}
                    </Typography>
                    <ProductStatusChip isActive={product.is_active} />
                  </Stack>
                  <Typography variant="caption" sx={{ color: 'text.secondary' }}>
                    {product.sku} · {product.category?.name}
                  </Typography>
                  <Stack
                    direction="row"
                    sx={{ gap: 1, justifyContent: 'space-between', alignItems: 'center' }}
                  >
                    <Typography variant="subtitle2" component="p">
                      {formatPrice(product.price)}
                    </Typography>
                    <Stack direction="row" sx={{ gap: 0.5, alignItems: 'center' }}>
                      <Typography variant="caption" sx={{ color: 'text.secondary' }}>
                        Stock:
                      </Typography>
                      <ProductStock product={product} />
                    </Stack>
                  </Stack>
                </Stack>
              </Stack>
            </CardContent>
            <Divider />
            <Box sx={{ px: 1 }}>
              <ProductActions product={product} onDelete={onDelete} />
            </Box>
          </Card>
        </li>
      ))}
    </Stack>
  );
}

function ProductCardsSkeleton() {
  return (
    <Stack sx={{ gap: 1.5 }} role="status" aria-label="Caricamento dei prodotti">
      {[0, 1, 2, 3].map((index) => (
        <Skeleton key={index} variant="rounded" sx={{ height: (theme) => theme.spacing(16) }} />
      ))}
    </Stack>
  );
}

export default function ProductsPage() {
  const { filters, update, reset } = useListFilters(parseProductFilters, serializeProductFilters);
  const apiParams = useMemo(() => toApiParams(serializeProductFilters(filters)), [filters]);
  const productsQuery = useAdminProducts(apiParams);
  const categoriesQuery = useAdminCategories();
  const isDesktop = useIsDesktop();
  const [deleting, setDeleting] = useState(null);
  const products = productsQuery.data?.data ?? [];
  const meta = productsQuery.data?.meta;
  const isEmpty = productsQuery.isSuccess && meta.total === 0;
  const categoryOptions = categoriesQuery.data
    ? categoriesQuery.data.map((category) => ({ value: category.slug, label: category.name }))
    : [{ value: filters.category, label: '' }].filter((option) => option.value !== '');

  if (
    productsQuery.isSuccess &&
    !productsQuery.isPlaceholderData &&
    meta.total > 0 &&
    filters.page > meta.last_page
  ) {
    return (
      <Navigate
        to={{ search: serializeProductFilters({ ...filters, page: meta.last_page }).toString() }}
        replace
      />
    );
  }

  const emptyState = (
    <AdminEmptyState
      icon={<SearchOutlined />}
      title="Nessun prodotto trovato"
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

  return (
    <Stack sx={{ gap: 3 }}>
      <Stack direction={{ xs: 'column', md: 'row' }} sx={{ gap: 2, alignItems: { md: 'center' } }}>
        <SearchField
          label="Cerca per nome o SKU"
          placeholder="Es. Cuffie Aria o CDS-AUD-001"
          value={filters.search}
          onSearch={(search) => update({ search })}
          sx={{
            flexGrow: { md: 1 },
            maxWidth: { md: (theme) => theme.layout.adminSearchMaxWidth },
          }}
        />
        <FilterSelect
          label="Categoria"
          value={filters.category}
          allLabel="Tutte"
          options={categoryOptions}
          onChange={(category) => update({ category })}
          sx={{ minWidth: { md: (theme) => theme.layout.adminFilterMinWidth } }}
        />
        <FilterSelect
          label="Stato"
          value={filters.status}
          allLabel="Tutti"
          options={productStatusOptions}
          onChange={(status) => update({ status })}
          sx={{ minWidth: { md: (theme) => theme.layout.adminFilterMinWidth } }}
        />
        <Box sx={{ flexGrow: 1, display: { xs: 'none', md: 'block' } }} />
        <Button
          component={RouterLink}
          to={paths.adminProductNew}
          variant="contained"
          startIcon={<Add />}
        >
          Nuovo prodotto
        </Button>
      </Stack>
      {productsQuery.isError ? (
        <ErrorState
          title="Non è stato possibile caricare i prodotti"
          message="Controlla la connessione e riprova."
          onRetry={() => productsQuery.refetch()}
        />
      ) : null}
      {isDesktop && !productsQuery.isError ? (
        <Card>
          <TableContainer
            aria-busy={productsQuery.isPlaceholderData}
            sx={{
              opacity: productsQuery.isPlaceholderData ? 0.6 : 1,
              transition: (theme) => theme.transitions.create('opacity'),
            }}
          >
            <Table aria-label="Prodotti">
              <AdminTableHead columns={COLUMNS} />
              {productsQuery.isPending ? <AdminTableSkeleton columns={COLUMNS} /> : null}
              {isEmpty ? (
                <TableBody>
                  <TableRow>
                    <TableCell colSpan={COLUMNS.length} sx={{ borderBottom: 0 }}>
                      {emptyState}
                    </TableCell>
                  </TableRow>
                </TableBody>
              ) : null}
              {products.length > 0 ? (
                <ProductRows products={products} onDelete={setDeleting} />
              ) : null}
            </Table>
          </TableContainer>
          {pagination}
        </Card>
      ) : null}
      {!isDesktop && productsQuery.isPending ? <ProductCardsSkeleton /> : null}
      {!isDesktop && isEmpty ? <Card>{emptyState}</Card> : null}
      {!isDesktop && products.length > 0 ? (
        <>
          <ProductCards products={products} onDelete={setDeleting} />
          {pagination}
        </>
      ) : null}
      {deleting ? (
        <DeleteProductDialog product={deleting} onClose={() => setDeleting(null)} />
      ) : null}
    </Stack>
  );
}
