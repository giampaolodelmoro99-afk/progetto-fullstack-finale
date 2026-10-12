import { describe, expect, it } from 'vitest';
import { formatCentsForInput, parseEuroToCents } from '@/utils/price';

describe('parseEuroToCents', () => {
  it.each([
    ['249,00', 24900],
    ['249,5', 24950],
    ['249', 24900],
    ['0,01', 1],
    ['9999,99', 999999],
    [' 12,30 ', 1230],
  ])('converts %s to %i cents', (value, cents) => {
    expect(parseEuroToCents(value)).toBe(cents);
  });

  it.each(['1.249', '249.00', '249,001', '', '10000,00', 'abc', ',50'])('rejects «%s»', (value) => {
    expect(parseEuroToCents(value)).toBeNull();
  });
});

describe('formatCentsForInput', () => {
  it.each([
    [34900, '349,00'],
    [124900, '1249,00'],
    [24950, '249,50'],
    [1, '0,01'],
  ])('formats %i cents as %s', (cents, text) => {
    expect(formatCentsForInput(cents)).toBe(text);
  });
});
