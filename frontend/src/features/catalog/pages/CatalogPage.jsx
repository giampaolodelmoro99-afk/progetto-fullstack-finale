import TuneOutlined from '@mui/icons-material/TuneOutlined';
import Button from '@mui/material/Button';
import Container from '@mui/material/Container';
import Grid from '@mui/material/Grid';
import Pagination from '@mui/material/Pagination';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useMemo, useState } from 'react';
import { Navigate, useSearchParams } from 'react-router';
import { paths } from '@/app/router/paths';
import { ProductGrid } from '@/components/data-display/ProductGrid';
import { ErrorState } from '@/components/feedback/ErrorState';
import {
  CATALOG_PAGE_SIZE,
  clearFilters,
  countActiveFilters,
  getSliderBounds,
  parseCatalogFilters,
  toApiParams,
  toSearchParams,
  updateFilters,
} from '@/features/catalog/catalogFilters';
import { CatalogBreadcrumbs } from '@/features/catalog/components/CatalogBreadcrumbs';
import { CatalogEmptyState } from '@/features/catalog/components/CatalogEmptyState';
import { CatalogFilters } from '@/features/catalog/components/CatalogFilters';
import { CategoryNotFound } from '@/features/catalog/components/CategoryNotFound';
import { FiltersDrawer } from '@/features/catalog/components/FiltersDrawer';
import { SortSelect } from '@/features/catalog/components/SortSelect';
import { useCategories } from '@/features/catalog/hooks/useCategories';
import { useProducts } from '@/features/catalog/hooks/useProducts';
import { useIsDesktop } from '@/hooks/useIsDesktop';
import { normalizeApiError } from '@/utils/normalizeApiError';

const PRODUCT_COLUMNS = { xs: 6, md: 4 };

function productCount(total) {
  return `${total} ${total === 1 ? 'prodotto' : 'prodotti'}`;
}

function resolveTitle(filters, categoriesQuery, products) {
  if (filters.category === null) {
    return filters.isNew ? 'Novità' : 'Tutti i prodotti';
  }

  if (categoriesQuery.isPending) {
    return null;
  }

  const category = categoriesQuery.data?.find((item) => item.slug === filters.category);

  if (category) {
    return category.name;
  }

  return products?.[0]?.category.name ?? 'Tutti i prodotti';
}

function isUnknownCategory(filters, categoriesQuery, productsQuery) {
  if (filters.category === null) {
    return false;
  }

  if (productsQuery.isError && normalizeApiError(productsQuery.error).fieldErrors.category) {
    return true;
  }

  return (
    categoriesQuery.isSuccess &&
    !categoriesQuery.data.some((category) => category.slug === filters.category)
  );
}

export default function CatalogPage() {
  const [searchParams, setSearchParams] = useSearchParams();
  const filters = useMemo(() => parseCatalogFilters(searchParams), [searchParams]);
  const apiParams = useMemo(() => toApiParams(filters), [filters]);
  const productsQuery = useProducts(apiParams);
  const categoriesQuery = useCategories();
  const isDesktop = useIsDesktop();
  const [drawerOpen, setDrawerOpen] = useState(false);

  if (isUnknownCategory(filters, categoriesQuery, productsQuery)) {
    return <CategoryNotFound />;
  }

  const result = productsQuery.data;
  const products = result?.data ?? [];
  const meta = result?.meta ?? null;
  const priceBounds = getSliderBounds(meta?.price_range);
  const title = resolveTitle(filters, categoriesQuery, products);
  const activeFilters = countActiveFilters(filters);

  function applyFilters(next) {
    setSearchParams(toSearchParams(next));
  }

  function handleChange(changes) {
    applyFilters(updateFilters(filters, changes));
  }

  function handleClear() {
    applyFilters(clearFilters(filters));
  }

  function handlePageChange(event, page) {
    applyFilters({ ...filters, page });
    window.scrollTo({ top: 0 });
  }

  function renderResults() {
    if (productsQuery.isPending) {
      return (
        <ProductGrid
          loading
          skeletonCount={CATALOG_PAGE_SIZE}
          size={PRODUCT_COLUMNS}
          label="Prodotti"
        />
      );
    }

    if (productsQuery.isError) {
      return (
        <ErrorState
          title="Non è stato possibile caricare i prodotti"
          message="Controlla la connessione e riprova."
          onRetry={() => productsQuery.refetch()}
        />
      );
    }

    if (products.length === 0 && meta.total > 0 && filters.page > 1) {
      return (
        <Navigate replace to={`${paths.products}?${toSearchParams({ ...filters, page: 1 })}`} />
      );
    }

    if (products.length === 0) {
      return <CatalogEmptyState onClear={handleClear} />;
    }

    return (
      <Stack sx={{ gap: 4, alignItems: 'center' }}>
        <ProductGrid
          products={products}
          size={PRODUCT_COLUMNS}
          headingComponent="h2"
          label="Prodotti"
          busy={productsQuery.isPlaceholderData}
        />
        {meta.last_page > 1 ? (
          <Pagination
            page={filters.page}
            count={meta.last_page}
            onChange={handlePageChange}
            shape="rounded"
            siblingCount={isDesktop ? 1 : 0}
          />
        ) : null}
      </Stack>
    );
  }

  return (
    <Container maxWidth="lg" sx={{ py: { xs: 3, md: 4 } }}>
      <Stack sx={{ gap: { xs: 2, md: 3 } }}>
        <CatalogBreadcrumbs items={[{ label: title }]} />
        <Stack
          direction={{ xs: 'column', sm: 'row' }}
          sx={{ justifyContent: 'space-between', alignItems: { sm: 'flex-end' }, gap: 2 }}
        >
          <Stack sx={{ gap: 0.5 }}>
            <Typography variant="h2" component="h1">
              {title ?? <Skeleton sx={{ width: '8ch' }} />}
            </Typography>
            {filters.search ? (
              <Typography variant="body1">Risultati per «{filters.search}»</Typography>
            ) : null}
            <Typography variant="body2" sx={{ color: 'text.secondary' }}>
              {meta ? productCount(meta.total) : <Skeleton sx={{ width: '10ch' }} />}
            </Typography>
          </Stack>
          <Stack direction="row" sx={{ gap: 1.5, alignItems: 'center' }}>
            {isDesktop ? null : (
              <Button
                variant="outlined"
                startIcon={<TuneOutlined />}
                onClick={() => setDrawerOpen(true)}
                sx={{ flex: 1, flexShrink: 0 }}
              >
                Filtri ({activeFilters})
              </Button>
            )}
            <SortSelect value={filters.sort} onChange={(sort) => handleChange({ sort })} />
          </Stack>
        </Stack>
        <Grid container spacing={4}>
          {isDesktop ? (
            <Grid component="aside" aria-label="Filtri" size={{ md: 3 }}>
              <CatalogFilters
                filters={filters}
                priceBounds={priceBounds}
                onChange={handleChange}
                onClear={handleClear}
              />
            </Grid>
          ) : null}
          <Grid size={{ xs: 12, md: 9 }}>{renderResults()}</Grid>
        </Grid>
      </Stack>
      {isDesktop ? null : (
        <FiltersDrawer
          open={drawerOpen}
          onClose={() => setDrawerOpen(false)}
          filters={filters}
          priceBounds={priceBounds}
          total={meta?.total ?? null}
          onChange={handleChange}
          onClear={handleClear}
        />
      )}
    </Container>
  );
}
