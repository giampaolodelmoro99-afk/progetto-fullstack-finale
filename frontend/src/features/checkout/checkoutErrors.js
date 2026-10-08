import { STEP } from '@/features/checkout/checkoutReducer';

const PAYMENT_PREFIX = 'payment.';

function paymentFieldErrors(fieldErrors) {
  return Object.fromEntries(
    Object.entries(fieldErrors)
      .filter(([field]) => field.startsWith(PAYMENT_PREFIX))
      .map(([field, message]) => [field.slice(PAYMENT_PREFIX.length), message]),
  );
}

function validationFailure(error) {
  const fieldErrors = error.fieldErrors ?? {};
  const payment = paymentFieldErrors(fieldErrors);

  if (Object.keys(payment).length > 0) {
    return { error: null, step: STEP.payment, fieldErrors: payment };
  }

  if (fieldErrors.address_id) {
    return {
      error: { kind: 'address', message: fieldErrors.address_id },
      step: STEP.address,
      fieldErrors: {},
      refreshAddresses: true,
    };
  }

  if (fieldErrors.terms_accepted) {
    return {
      error: null,
      step: STEP.review,
      fieldErrors: { terms_accepted: fieldErrors.terms_accepted },
    };
  }

  return { error: { kind: 'generic', message: error.message }, step: STEP.review, fieldErrors: {} };
}

export function checkoutFailure(error) {
  if (error.status === 422) {
    return validationFailure(error);
  }

  if (error.status === 402) {
    return { error: { kind: 'payment_failed', message: error.message }, step: STEP.review };
  }

  if (error.status === 409 && error.code === 'insufficient_stock') {
    return {
      error: { kind: 'insufficient_stock', message: error.message, products: error.products ?? [] },
      step: STEP.review,
    };
  }

  if (error.status === 409) {
    return { error: { kind: 'cart_empty', message: error.message }, step: STEP.review };
  }

  if (error.status === 403) {
    return { error: null, step: STEP.address, refreshUser: true };
  }

  return { error: { kind: 'generic', message: error.message }, step: STEP.review };
}

export function stockIssueText({ product, requested, available }) {
  if (available === 0) {
    return `${product}: non più disponibile`;
  }

  if (available === 1) {
    return `${product}: disponibile 1 su ${requested} richiesti`;
  }

  return `${product}: disponibili ${available} su ${requested} richiesti`;
}
