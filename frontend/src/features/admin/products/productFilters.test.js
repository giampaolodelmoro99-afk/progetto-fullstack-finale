import { describe, expect, it } from 'vitest';
import {
  parseProductFilters,
  serializeProductFilters,
} from '@/features/admin/products/productFilters';

function parse(query) {
  return parseProductFilters(new URLSearchParams(query));
}

describe('productFilters', () => {
  it('reads the defaults from an empty query string', () => {
    expect(parse('')).toEqual({ search: '', category: '', status: '', page: 1, perPage: 10 });
  });

  it('reads every filter', () => {
    expect(parse('search=%20Cuffie%20&category=audio&status=inactive&page=3&per_page=25')).toEqual({
      search: 'Cuffie',
      category: 'audio',
      status: 'inactive',
      page: 3,
      perPage: 25,
    });
  });

  it('ignores unknown values', () => {
    expect(parse('status=deleted&page=-2&per_page=30')).toEqual({
      search: '',
      category: '',
      status: '',
      page: 1,
      perPage: 10,
    });
  });

  it('leaves the default values out of the query string', () => {
    expect(
      serializeProductFilters({ search: '', category: '', status: '', page: 1, perPage: 10 }),
    ).toHaveProperty('size', 0);
    expect(
      serializeProductFilters({
        search: 'aria',
        category: 'audio',
        status: 'active',
        page: 2,
        perPage: 50,
      }).toString(),
    ).toBe('search=aria&category=audio&status=active&page=2&per_page=50');
  });
});
