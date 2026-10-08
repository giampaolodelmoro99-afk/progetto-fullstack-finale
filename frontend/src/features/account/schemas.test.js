import { describe, expect, it } from 'vitest';
import { addressSchema } from '@/features/account/schemas';

const VALID = {
  full_name: 'Giulia Ferri',
  street: 'Via San Martino 42',
  city: 'Pisa',
  postal_code: '56125',
  province: 'PI',
  phone: '+39 333 123 4567',
  is_default: false,
};

function parse(values) {
  return addressSchema.safeParse({ ...VALID, ...values });
}

function errorsFor(values) {
  const result = parse(values);

  return result.success
    ? {}
    : result.error.issues.reduce(
        (errors, issue) =>
          issue.path[0] in errors ? errors : { ...errors, [issue.path[0]]: issue.message },
        {},
      );
}

describe('addressSchema', () => {
  it('accepts a valid italian address', () => {
    expect(errorsFor({})).toEqual({});
    expect(errorsFor({ phone: '3331234567' })).toEqual({});
  });

  it('turns the province into capital letters', () => {
    expect(parse({ province: ' pi ' }).data.province).toBe('PI');
  });

  it.each(['5612', '561255', '5612a'])('rejects the postal code %s', (postalCode) => {
    expect(errorsFor({ postal_code: postalCode })).toEqual({
      postal_code: 'Il CAP deve avere 5 cifre',
    });
  });

  it.each(['PIS', 'P1'])('rejects the province %s', (province) => {
    expect(errorsFor({ province })).toEqual({
      province: 'Usa la sigla di due lettere della provincia, per esempio PI.',
    });
  });

  it.each([
    ['', 'Inserisci il numero di telefono.'],
    ['+39 333 123 4567 8901', 'Usa al massimo 20 caratteri.'],
    ['333 abc', 'Usa solo cifre e spazi, con un + iniziale facoltativo.'],
    ['333+123', 'Usa solo cifre e spazi, con un + iniziale facoltativo.'],
  ])('rejects the phone %s', (phone, message) => {
    expect(errorsFor({ phone })).toEqual({ phone: message });
  });
});
