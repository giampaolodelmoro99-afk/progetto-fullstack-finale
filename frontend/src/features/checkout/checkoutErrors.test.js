import { describe, expect, it } from 'vitest';
import { checkoutFailure, stockIssueText } from '@/features/checkout/checkoutErrors';

function apiError(overrides) {
  return { status: 500, message: 'Errore.', fieldErrors: {}, code: null, ...overrides };
}

describe('stockIssueText', () => {
  it('describes a product that is no longer available', () => {
    expect(stockIssueText({ product: 'Radio Onda Portatile', requested: 1, available: 0 })).toBe(
      'Radio Onda Portatile: non più disponibile',
    );
  });

  it('uses the singular with one piece left', () => {
    expect(stockIssueText({ product: 'Caricatore Brezza 65 W', requested: 2, available: 1 })).toBe(
      'Caricatore Brezza 65 W: disponibile 1 su 2 richiesti',
    );
  });

  it('uses the plural with more pieces left', () => {
    expect(stockIssueText({ product: 'Cavo Seta USB-C 1 m', requested: 5, available: 3 })).toBe(
      'Cavo Seta USB-C 1 m: disponibili 3 su 5 richiesti',
    );
  });
});

describe('checkoutFailure', () => {
  it('maps the card errors to the payment step', () => {
    const failure = checkoutFailure(
      apiError({ status: 422, fieldErrors: { 'payment.number': 'Numero non valido.' } }),
    );

    expect(failure).toEqual({
      error: null,
      step: 1,
      fieldErrors: { number: 'Numero non valido.' },
    });
  });

  it('maps an address error to the address step', () => {
    const failure = checkoutFailure(
      apiError({ status: 422, fieldErrors: { address_id: 'Indirizzo non valido.' } }),
    );

    expect(failure).toEqual({
      error: { kind: 'address', message: 'Indirizzo non valido.' },
      step: 0,
      fieldErrors: {},
      refreshAddresses: true,
    });
  });

  it('maps a terms error under the checkbox of the review step', () => {
    const failure = checkoutFailure(
      apiError({ status: 422, fieldErrors: { terms_accepted: 'Devi accettare le condizioni.' } }),
    );

    expect(failure).toEqual({
      error: null,
      step: 2,
      fieldErrors: { terms_accepted: 'Devi accettare le condizioni.' },
    });
  });

  it('keeps the products of a stock conflict', () => {
    const products = [{ product: 'Radio Onda', requested: 1, available: 0 }];
    const failure = checkoutFailure(
      apiError({ status: 409, code: 'insufficient_stock', message: 'Cambiata.', products }),
    );

    expect(failure).toEqual({
      error: { kind: 'insufficient_stock', message: 'Cambiata.', products },
      step: 2,
    });
  });

  it('asks to reload the user after a 403', () => {
    expect(checkoutFailure(apiError({ status: 403 }))).toEqual({
      error: null,
      step: 0,
      refreshUser: true,
    });
  });
});
