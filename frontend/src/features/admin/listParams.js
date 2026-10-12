export const PER_PAGE_OPTIONS = [10, 25, 50];

export const DEFAULT_PER_PAGE = 10;

export const MAX_SEARCH_LENGTH = 100;

export function parseSearch(value) {
  return (value ?? '').trim().slice(0, MAX_SEARCH_LENGTH);
}

export function parsePage(value) {
  if (value === null || !/^\d+$/.test(value)) {
    return 1;
  }

  const page = Number(value);

  return Number.isSafeInteger(page) ? Math.max(1, page) : 1;
}

export function parsePerPage(value) {
  const perPage = Number(value);

  return PER_PAGE_OPTIONS.includes(perPage) ? perPage : DEFAULT_PER_PAGE;
}

export function parseOption(value, allowed) {
  return allowed.includes(value) ? value : '';
}

export function setIfPresent(params, key, value) {
  if (value !== '' && value !== null && value !== undefined && value !== false) {
    params.set(key, value === true ? '1' : String(value));
  }
}

export function setPagination(params, filters) {
  if (filters.page > 1) {
    params.set('page', String(filters.page));
  }

  if (filters.perPage !== DEFAULT_PER_PAGE) {
    params.set('per_page', String(filters.perPage));
  }
}

export function toApiParams(searchParams) {
  return Object.fromEntries(searchParams.entries());
}
