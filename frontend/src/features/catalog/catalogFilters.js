export const CATALOG_PAGE_SIZE = 12;

export const DEFAULT_SORT = 'newest';

export const PRICE_STEP = 100;

export const sortOptions = [
  { value: 'newest', label: 'Novità' },
  { value: 'price_asc', label: 'Prezzo crescente' },
  { value: 'price_desc', label: 'Prezzo decrescente' },
];

const SORT_VALUES = new Set(sortOptions.map((option) => option.value));
const MAX_PRICE = 999999;
const MAX_SEARCH_LENGTH = 100;
const TRUE_VALUES = new Set(['1', 'true']);

function parsePrice(value) {
  if (value === null || !/^\d+$/.test(value)) {
    return null;
  }

  const cents = Number(value);

  return cents <= MAX_PRICE ? cents : null;
}

function parseFlag(value) {
  return value !== null && TRUE_VALUES.has(value.toLowerCase());
}

function parsePage(value) {
  if (value === null || !/^\d+$/.test(value)) {
    return 1;
  }

  const page = Number(value);

  return Number.isSafeInteger(page) ? Math.max(1, page) : 1;
}

export function parseCatalogFilters(searchParams) {
  const search = (searchParams.get('search') ?? '').trim().slice(0, MAX_SEARCH_LENGTH);
  const category = (searchParams.get('category') ?? '').trim();
  const sort = searchParams.get('sort');
  let minPrice = parsePrice(searchParams.get('min_price'));
  let maxPrice = parsePrice(searchParams.get('max_price'));

  if (minPrice !== null && maxPrice !== null && maxPrice < minPrice) {
    minPrice = null;
    maxPrice = null;
  }

  return {
    search: search === '' ? null : search,
    category: category === '' ? null : category,
    minPrice,
    maxPrice,
    inStock: parseFlag(searchParams.get('in_stock')),
    isNew: parseFlag(searchParams.get('new')),
    sort: SORT_VALUES.has(sort) ? sort : DEFAULT_SORT,
    page: parsePage(searchParams.get('page')),
  };
}

export function toSearchParams(filters) {
  const params = new URLSearchParams();

  if (filters.search) {
    params.set('search', filters.search);
  }

  if (filters.category) {
    params.set('category', filters.category);
  }

  if (filters.minPrice !== null) {
    params.set('min_price', String(filters.minPrice));
  }

  if (filters.maxPrice !== null) {
    params.set('max_price', String(filters.maxPrice));
  }

  if (filters.inStock) {
    params.set('in_stock', '1');
  }

  if (filters.isNew) {
    params.set('new', '1');
  }

  if (filters.sort !== DEFAULT_SORT) {
    params.set('sort', filters.sort);
  }

  if (filters.page > 1) {
    params.set('page', String(filters.page));
  }

  return params;
}

export function updateFilters(filters, changes) {
  return { ...filters, page: 1, ...changes };
}

export function clearFilters(filters) {
  return {
    search: null,
    category: null,
    minPrice: null,
    maxPrice: null,
    inStock: false,
    isNew: false,
    sort: filters.sort,
    page: 1,
  };
}

export function countActiveFilters(filters) {
  return [
    filters.category !== null,
    filters.minPrice !== null || filters.maxPrice !== null,
    filters.inStock,
    filters.isNew,
  ].filter(Boolean).length;
}

export function toApiParams(filters) {
  const params = { sort: filters.sort, page: filters.page, per_page: CATALOG_PAGE_SIZE };

  if (filters.search) {
    params.search = filters.search;
  }

  if (filters.category) {
    params.category = filters.category;
  }

  if (filters.minPrice !== null) {
    params.min_price = filters.minPrice;
  }

  if (filters.maxPrice !== null) {
    params.max_price = filters.maxPrice;
  }

  if (filters.inStock) {
    params.in_stock = 1;
  }

  if (filters.isNew) {
    params.new = 1;
  }

  return params;
}

export function getSliderBounds(priceRange) {
  if (!priceRange || priceRange.min === null || priceRange.max === null) {
    return null;
  }

  const min = Math.floor(priceRange.min / PRICE_STEP) * PRICE_STEP;
  const max = Math.ceil(priceRange.max / PRICE_STEP) * PRICE_STEP;

  return max > min ? { min, max } : null;
}
