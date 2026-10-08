import { describe, expect, it } from 'vitest';
import { passesLuhn } from '@/features/checkout/utils/luhn';

describe('passesLuhn', () => {
  it.each([
    ['4242424242424242', true],
    ['4000000000000002', true],
    ['4242424242424241', false],
    ['4242 4242 4242 4242', false],
    ['', false],
  ])('checks %s', (digits, expected) => {
    expect(passesLuhn(digits)).toBe(expected);
  });
});
