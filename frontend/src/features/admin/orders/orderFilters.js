import {
  parseOption,
  parsePage,
  parsePerPage,
  parseSearch,
  setIfPresent,
  setPagination,
} from '@/features/admin/listParams';

const STATUS_VALUES = ['pending', 'paid', 'shipped', 'delivered', 'cancelled'];

const PAID_MONTH_PATTERN = /^\d{4}-(0[1-9]|1[0-2])$/;

export function parseOrderFilters(searchParams) {
  const paidMonth = searchParams.get('paid_month') ?? '';

  return {
    search: parseSearch(searchParams.get('search')),
    status: parseOption(searchParams.get('status'), STATUS_VALUES),
    failedPayment: searchParams.get('failed_payment') === '1',
    paidMonth: PAID_MONTH_PATTERN.test(paidMonth) ? paidMonth : '',
    page: parsePage(searchParams.get('page')),
    perPage: parsePerPage(searchParams.get('per_page')),
  };
}

export function serializeOrderFilters(filters) {
  const params = new URLSearchParams();
  setIfPresent(params, 'status', filters.status);
  setIfPresent(params, 'search', filters.search);
  setIfPresent(params, 'failed_payment', filters.failedPayment);
  setIfPresent(params, 'paid_month', filters.paidMonth);
  setPagination(params, filters);

  return params;
}
