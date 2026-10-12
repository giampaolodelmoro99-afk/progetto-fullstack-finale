import {
  parseOption,
  parsePage,
  parsePerPage,
  parseSearch,
  setIfPresent,
  setPagination,
} from '@/features/admin/listParams';

export const productStatusOptions = [
  { value: 'active', label: 'Attivo' },
  { value: 'inactive', label: 'Disattivato' },
];

const STATUS_VALUES = productStatusOptions.map((option) => option.value);

export function parseProductFilters(searchParams) {
  return {
    search: parseSearch(searchParams.get('search')),
    category: (searchParams.get('category') ?? '').trim(),
    status: parseOption(searchParams.get('status'), STATUS_VALUES),
    page: parsePage(searchParams.get('page')),
    perPage: parsePerPage(searchParams.get('per_page')),
  };
}

export function serializeProductFilters(filters) {
  const params = new URLSearchParams();
  setIfPresent(params, 'search', filters.search);
  setIfPresent(params, 'category', filters.category);
  setIfPresent(params, 'status', filters.status);
  setPagination(params, filters);

  return params;
}
