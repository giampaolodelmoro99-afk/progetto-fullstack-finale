import { describe, expect, it } from 'vitest';
import { checkoutReducer, initialCheckoutState } from '@/features/checkout/checkoutReducer';

const CARD = { holder: 'Giulia Ferri', number: '4242 4242 4242 4242', expiry: '08/29', cvc: '123' };

describe('checkoutReducer', () => {
  it('starts from the address step with empty card fields', () => {
    expect(initialCheckoutState.step).toBe(0);
    expect(initialCheckoutState.payment).toEqual({ holder: '', number: '', expiry: '', cvc: '' });
    expect(initialCheckoutState.termsAccepted).toBe(false);
  });

  it('moves forward when the address and the card are confirmed', () => {
    const afterAddress = checkoutReducer(initialCheckoutState, {
      type: 'addressConfirmed',
      addressId: 12,
    });
    const afterPayment = checkoutReducer(afterAddress, { type: 'paymentConfirmed', payment: CARD });

    expect(afterAddress).toMatchObject({ step: 1, addressId: 12 });
    expect(afterPayment).toMatchObject({ step: 2, payment: CARD });
  });

  it('keeps the card when going back to the payment step after a failure', () => {
    const failed = checkoutReducer(
      { ...initialCheckoutState, step: 2, payment: CARD },
      { type: 'failed', error: { kind: 'payment_failed', message: 'Rifiutata.' }, step: 2 },
    );
    const back = checkoutReducer(failed, { type: 'goToStep', step: 1 });

    expect(back).toMatchObject({ step: 1, payment: CARD, error: null });
  });

  it('forgets the card once the order is placed', () => {
    const placed = checkoutReducer(
      { ...initialCheckoutState, step: 2, payment: CARD },
      { type: 'placed' },
    );

    expect(placed.payment).toEqual({ holder: '', number: '', expiry: '', cvc: '' });
  });
});
