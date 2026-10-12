import { describe, expect, it } from 'vitest';
import { parseOrderFilters, serializeOrderFilters } from '@/features/admin/orders/orderFilters';

const DEFAULTS = {
  search: '',
  status: '',
  failedPayment: false,
  paidMonth: '',
  page: 1,
  perPage: 10,
};

function parse(query) {
  return parseOrderFilters(new URLSearchParams(query));
}

describe('orderFilters', () => {
  it('reads the defaults from an empty query string', () => {
    expect(parse('')).toEqual(DEFAULTS);
  });

  it('reads every filter', () => {
    expect(
      parse('status=paid&search=ORD-2026&failed_payment=1&paid_month=2026-09&page=2&per_page=50'),
    ).toEqual({
      search: 'ORD-2026',
      status: 'paid',
      failedPayment: true,
      paidMonth: '2026-09',
      page: 2,
      perPage: 50,
    });
  });

  it.each(['2026-9', '2026-13', 'settembre'])('ignores the month %s', (month) => {
    expect(parse(`paid_month=${month}`).paidMonth).toBe('');
  });

  it('ignores an unknown status', () => {
    expect(parse('status=lost').status).toBe('');
  });

  it('writes only the filters that differ from the defaults', () => {
    expect(serializeOrderFilters(DEFAULTS).toString()).toBe('');
    expect(
      serializeOrderFilters({
        ...DEFAULTS,
        status: 'cancelled',
        failedPayment: true,
        paidMonth: '2026-09',
      }).toString(),
    ).toBe('status=cancelled&failed_payment=1&paid_month=2026-09');
  });
});
