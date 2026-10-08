import { describe, expect, it } from 'vitest';
import { getInitials } from '@/utils/initials';

describe('getInitials', () => {
  it.each([
    ['Marta Lenzi', 'ML'],
    ['Giulia Maria Ferri', 'GF'],
    ['davide', 'D'],
    ['  Sara   Galli ', 'SG'],
    ['', ''],
    [null, ''],
  ])('returns the initials of %s', (name, expected) => {
    expect(getInitials(name)).toBe(expected);
  });
});
