import { emptyPayment } from '@/features/checkout/schemas';

export const CHECKOUT_STEPS = ['Indirizzo', 'Pagamento', 'Riepilogo'];

export const STEP = { address: 0, payment: 1, review: 2 };

export const STEP_TITLE_IDS = ['address-step-title', 'payment-step-title', 'review-step-title'];

export const initialCheckoutState = {
  step: STEP.address,
  addressId: null,
  payment: emptyPayment,
  termsAccepted: false,
  error: null,
  fieldErrors: {},
};

export function checkoutReducer(state, action) {
  switch (action.type) {
    case 'addressConfirmed':
      return { ...state, addressId: action.addressId, step: STEP.payment, fieldErrors: {} };
    case 'paymentConfirmed':
      return { ...state, payment: action.payment, step: STEP.review, fieldErrors: {} };
    case 'goToStep':
      return { ...state, step: action.step, error: null };
    case 'termsChanged':
      return {
        ...state,
        termsAccepted: action.accepted,
        fieldErrors: { ...state.fieldErrors, terms_accepted: undefined },
      };
    case 'submitted':
      return { ...state, error: null, fieldErrors: {} };
    case 'failed':
      return {
        ...state,
        error: action.error,
        step: action.step ?? state.step,
        fieldErrors: action.fieldErrors ?? {},
      };
    case 'placed':
      return { ...state, payment: emptyPayment, error: null };
    default:
      return state;
  }
}
