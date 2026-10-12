import { describe, expect, it } from 'vitest';
import {
  formatDate,
  formatDateTime,
  formatDateTimeShort,
  formatDayTimeShort,
  formatItemCount,
  formatMonthYear,
  formatOrderCount,
  formatPostalLine,
  formatPrice,
} from '@/utils/format';

const SPACE = /\s/g;

function normalizeSpaces(text) {
  return text.replace(SPACE, ' ');
}

describe('formatPrice', () => {
  it.each([
    [34900, '349,00 €'],
    [124900, '1.249,00 €'],
    [0, '0,00 €'],
    [5, '0,05 €'],
    [99999900, '999.999,00 €'],
  ])('formats %i cents as %s', (cents, expected) => {
    expect(normalizeSpaces(formatPrice(cents))).toBe(expected);
  });
});

describe('dates', () => {
  it('formats a date in long italian form', () => {
    expect(formatDate('2026-09-29T09:14:00+02:00')).toBe('29 settembre 2026');
  });

  it('formats date and time', () => {
    expect(formatDateTime('2026-09-29T09:14:00+02:00')).toBe('29 settembre 2026, 09:14');
  });

  it('formats date and time in short form', () => {
    expect(formatDateTimeShort('2026-09-29T16:52:00+02:00')).toBe('29 set 2026, 16:52');
  });

  it('formats day and time without the year', () => {
    expect(formatDayTimeShort('2026-09-29T16:52:00+02:00')).toBe('29 set, 16:52');
  });

  it('uses the italian time zone whatever the offset of the value', () => {
    expect(formatDateTime('2026-09-30T22:30:00Z')).toBe('1 ottobre 2026, 00:30');
  });

  it('formats a month with a capital initial', () => {
    expect(formatMonthYear('2026-09')).toBe('Settembre 2026');
    expect(formatMonthYear('2027-01')).toBe('Gennaio 2027');
  });

  it('counts the items in singular and plural', () => {
    expect(formatItemCount(1)).toBe('1 articolo');
    expect(formatItemCount(4)).toBe('4 articoli');
    expect(formatItemCount(0)).toBe('0 articoli');
  });

  it('formats the number of orders with singular and plural', () => {
    expect(formatOrderCount(1)).toBe('1 ordine');
    expect(formatOrderCount(48)).toBe('48 ordini');
    expect(formatOrderCount(0)).toBe('0 ordini');
  });

  it('formats the postal line of an italian address', () => {
    expect(
      formatPostalLine({ postal_code: '56125', city: 'Pisa', province: 'PI', country: 'IT' }),
    ).toBe('56125 Pisa (PI), Italia');
  });
});
