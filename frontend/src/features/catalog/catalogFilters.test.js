import { describe, expect, it } from 'vitest';
import {
  clearFilters,
  countActiveFilters,
  getSliderBounds,
  parseCatalogFilters,
  toApiParams,
  toSearchParams,
  updateFilters,
} from '@/features/catalog/catalogFilters';

function parse(query) {
  return parseCatalogFilters(new URLSearchParams(query));
}

describe('catalog filters', () => {
  it('reads the filters from the query string', () => {
    expect(
      parse(
        'search=cuffie&category=audio&min_price=1000&max_price=50000&in_stock=1&new=true&sort=price_asc&page=3',
      ),
    ).toEqual({
      search: 'cuffie',
      category: 'audio',
      minPrice: 1000,
      maxPrice: 50000,
      inStock: true,
      isNew: true,
      sort: 'price_asc',
      page: 3,
    });
  });

  it('uses the defaults for missing or malformed values', () => {
    expect(
      parse('search=%20%20&min_price=abc&max_price=1000000&in_stock=forse&sort=popular&page=0'),
    ).toEqual({
      search: null,
      category: null,
      minPrice: null,
      maxPrice: null,
      inStock: false,
      isNew: false,
      sort: 'newest',
      page: 1,
    });
  });

  it('ignores a page number beyond the safe integers', () => {
    expect(parse('page=99999999999999999999').page).toBe(1);
  });

  it('drops a price range with the maximum below the minimum', () => {
    expect(parse('min_price=5000&max_price=1000')).toMatchObject({
      minPrice: null,
      maxPrice: null,
    });
  });

  it('writes only the values different from the defaults', () => {
    expect(toSearchParams(parse('sort=newest&page=1')).toString()).toBe('');
    expect(
      toSearchParams(parse('category=audio&in_stock=true&sort=price_desc&page=2')).toString(),
    ).toBe('category=audio&in_stock=1&sort=price_desc&page=2');
  });

  it('goes back to the first page when a filter changes', () => {
    expect(updateFilters(parse('category=audio&page=4'), { inStock: true })).toMatchObject({
      category: 'audio',
      inStock: true,
      page: 1,
    });
  });

  it('clears every filter but keeps the sort', () => {
    expect(
      toSearchParams(
        clearFilters(parse('search=aria&category=audio&new=1&sort=price_asc&page=2')),
      ).toString(),
    ).toBe('sort=price_asc');
  });

  it('counts category, price, availability and new arrivals as active filters', () => {
    expect(countActiveFilters(parse('search=aria'))).toBe(0);
    expect(
      countActiveFilters(parse('category=audio&min_price=100&max_price=900&in_stock=1&new=1')),
    ).toBe(4);
  });

  it('builds the api parameters with twelve products per page', () => {
    expect(toApiParams(parse('category=audio&max_price=9900&new=1'))).toEqual({
      category: 'audio',
      max_price: 9900,
      new: 1,
      sort: 'newest',
      page: 1,
      per_page: 12,
    });
  });

  it.each([
    [
      { min: 1900, max: 149900 },
      { min: 1900, max: 149900 },
    ],
    [
      { min: 1950, max: 149920 },
      { min: 1900, max: 150000 },
    ],
    [{ min: 5900, max: 5900 }, null],
    [{ min: null, max: null }, null],
    [undefined, null],
  ])('rounds the price range %o to whole euros', (priceRange, expected) => {
    expect(getSliderBounds(priceRange)).toEqual(expected);
  });
});
