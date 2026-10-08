import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { paymentSchema } from '@/features/checkout/schemas';

const VALID = {
  holder: 'Giulia Ferri',
  number: '4242 4242 4242 4242',
  expiry: '08/29',
  cvc: '123',
};

function errorsFor(values) {
  const result = paymentSchema.safeParse({ ...VALID, ...values });

  return result.success
    ? {}
    : Object.fromEntries(result.error.issues.map((issue) => [issue.path[0], issue.message]));
}

describe('paymentSchema', () => {
  beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date(2026, 9, 8, 12, 0));
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('accepts the demo cards, with or without spaces', () => {
    expect(errorsFor({})).toEqual({});
    expect(errorsFor({ number: '4000000000000002' })).toEqual({});
  });

  it.each([
    ['4242 4242 4242 4241', 'Controlla il numero della carta.'],
    ['424242424242', 'Il numero della carta deve avere da 13 a 19 cifre.'],
    ['42424242424242424242', 'Il numero della carta deve avere da 13 a 19 cifre.'],
    ['4242 4242 abcd 4242', 'Il numero della carta deve avere da 13 a 19 cifre.'],
  ])('rejects the card number %s', (number, message) => {
    expect(errorsFor({ number })).toEqual({ number: message });
  });

  it('accepts the current month as expiry', () => {
    expect(errorsFor({ expiry: '10/26' })).toEqual({});
  });

  it.each([
    ['09/26', 'La carta è scaduta.'],
    ['13/29', 'Usa il formato MM/AA, per esempio 08/29.'],
    ['8/29', 'Usa il formato MM/AA, per esempio 08/29.'],
  ])('rejects the expiry %s', (expiry, message) => {
    expect(errorsFor({ expiry })).toEqual({ expiry: message });
  });

  it.each(['12', '1234'])('rejects the CVC %s', (cvc) => {
    expect(errorsFor({ cvc })).toEqual({ cvc: 'Il CVC deve avere 3 cifre.' });
  });

  it('requires the card holder', () => {
    expect(errorsFor({ holder: '  ' })).toEqual({
      holder: "Inserisci l'intestatario della carta.",
    });
  });
});
