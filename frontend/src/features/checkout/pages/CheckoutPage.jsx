import LockOutlined from '@mui/icons-material/LockOutlined';
import Button from '@mui/material/Button';
import Container from '@mui/material/Container';
import Grid from '@mui/material/Grid';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useCallback, useEffect, useReducer, useRef, useState } from 'react';
import { Navigate, useNavigate } from 'react-router';
import { paths } from '@/app/router/paths';
import { ErrorState } from '@/components/feedback/ErrorState';
import { MobileActionBar } from '@/components/layout/MobileActionBar';
import { useAddresses } from '@/features/account/hooks/useAddresses';
import { useAuth } from '@/features/auth/hooks/useAuth';
import { useCart } from '@/features/cart/hooks/useCart';
import { checkoutFailure } from '@/features/checkout/checkoutErrors';
import {
  STEP,
  STEP_TITLE_IDS,
  checkoutReducer,
  initialCheckoutState,
} from '@/features/checkout/checkoutReducer';
import { ADDRESS_FORM_ID, AddressStep } from '@/features/checkout/components/AddressStep';
import { CheckoutAlert } from '@/features/checkout/components/CheckoutAlert';
import { CheckoutProgress } from '@/features/checkout/components/CheckoutProgress';
import { CheckoutSkeleton } from '@/features/checkout/components/CheckoutSkeleton';
import { MobileOrderSummary } from '@/features/checkout/components/MobileOrderSummary';
import { OrderSummaryCard } from '@/features/checkout/components/OrderSummaryCard';
import { PAYMENT_FORM_ID, PaymentStep } from '@/features/checkout/components/PaymentStep';
import { REVIEW_FORM_ID, ReviewStep } from '@/features/checkout/components/ReviewStep';
import { VerifyEmailCard } from '@/features/checkout/components/VerifyEmailCard';
import { usePlaceOrder } from '@/features/checkout/hooks/usePlaceOrder';
import { useIsDesktop } from '@/hooks/useIsDesktop';
import { formatPrice } from '@/utils/format';

function isBlocked(cart) {
  return cart.items.length === 0 || !cart.can_checkout;
}

function PageFrame({ children }) {
  return (
    <Container maxWidth="lg" sx={{ py: { xs: 3, md: 5 } }}>
      <Stack sx={{ gap: { xs: 2, md: 4 } }}>
        <Typography variant="h2" component="h1">
          Checkout
        </Typography>
        {children}
      </Stack>
    </Container>
  );
}

function CheckoutContent({ cart }) {
  const isDesktop = useIsDesktop();
  const navigate = useNavigate();
  const { isEmailVerified, refetch: refetchUser } = useAuth();
  const addressesQuery = useAddresses();
  const [state, dispatch] = useReducer(checkoutReducer, initialCheckoutState);
  const { step, addressId, payment, termsAccepted, error, fieldErrors } = state;

  const getPayload = useCallback(
    () => ({
      address_id: addressId,
      payment: { ...payment },
      terms_accepted: termsAccepted,
    }),
    [addressId, payment, termsAccepted],
  );

  const placeOrder = usePlaceOrder(getPayload, {
    onPlaced: (order) => {
      dispatch({ type: 'placed' });
      navigate(paths.checkoutSuccess(order.id), { replace: true });
    },
  });

  const firstRender = useRef(true);

  useEffect(() => {
    if (firstRender.current) {
      firstRender.current = false;

      return;
    }

    window.scrollTo({ top: 0 });

    if (!error) {
      document.getElementById(STEP_TITLE_IDS[step])?.focus({ preventScroll: true });
    }
  }, [step, error]);

  const canPay =
    termsAccepted && cart.can_checkout && cart.items.length > 0 && !placeOrder.isPending;
  const address = addressesQuery.data?.find((item) => item.id === addressId) ?? null;
  const showVerify = !isEmailVerified;

  function pay() {
    dispatch({ type: 'submitted' });
    placeOrder.mutate(undefined, {
      onError: (placeError) => {
        const failure = checkoutFailure(placeError);

        if (failure.refreshUser) {
          refetchUser();
        }

        if (failure.refreshAddresses) {
          addressesQuery.refetch();
        }

        dispatch({ type: 'failed', ...failure });
      },
    });
  }

  function goToStep(target) {
    dispatch({ type: 'goToStep', step: target });
  }

  let content;

  if (showVerify) {
    content = <VerifyEmailCard />;
  } else if (step === STEP.address) {
    content = (
      <AddressStep
        addressId={addressId}
        error={error?.kind === 'address' ? error.message : null}
        primaryInline={isDesktop}
        onConfirm={(id) => dispatch({ type: 'addressConfirmed', addressId: id })}
      />
    );
  } else if (step === STEP.payment) {
    content = (
      <PaymentStep
        payment={payment}
        fieldErrors={fieldErrors}
        primaryInline={isDesktop}
        onConfirm={(values) => dispatch({ type: 'paymentConfirmed', payment: values })}
        onBack={() => goToStep(STEP.address)}
      />
    );
  } else {
    content = (
      <Stack sx={{ gap: 2 }}>
        <CheckoutAlert
          error={error}
          availabilityChanged={!cart.can_checkout}
          onEditCard={() => goToStep(STEP.payment)}
        />
        <ReviewStep
          cart={cart}
          address={address}
          payment={payment}
          termsAccepted={termsAccepted}
          termsError={fieldErrors.terms_accepted}
          canPay={canPay}
          isPaying={placeOrder.isPending}
          primaryInline={isDesktop}
          onTermsChange={(accepted) => dispatch({ type: 'termsChanged', accepted })}
          onEdit={goToStep}
          onPay={pay}
        />
      </Stack>
    );
  }

  const mobileAction = [
    { form: ADDRESS_FORM_ID, label: 'Continua al pagamento' },
    { form: PAYMENT_FORM_ID, label: 'Continua al riepilogo' },
    { form: REVIEW_FORM_ID, label: `Paga ${formatPrice(cart.summary.total)}` },
  ][step];

  return (
    <>
      <CheckoutProgress step={showVerify ? STEP.address : step} isDesktop={isDesktop} />
      {isDesktop ? (
        <Grid container spacing={4} sx={{ alignItems: 'flex-start' }}>
          <Grid size={8}>{content}</Grid>
          <Grid size={4}>
            <OrderSummaryCard cart={cart} />
          </Grid>
        </Grid>
      ) : (
        <Stack sx={{ gap: 2 }}>
          <MobileOrderSummary cart={cart} />
          {content}
        </Stack>
      )}
      {isDesktop || showVerify ? null : (
        <MobileActionBar total={cart.summary.total}>
          <Button
            type="submit"
            form={mobileAction.form}
            variant="contained"
            startIcon={step === STEP.review ? <LockOutlined /> : null}
            disabled={step === STEP.review && !canPay}
            loading={step === STEP.review && placeOrder.isPending}
          >
            {mobileAction.label}
          </Button>
        </MobileActionBar>
      )}
    </>
  );
}

export default function CheckoutPage() {
  const cartQuery = useCart();
  const cart = cartQuery.data;
  const [entry, setEntry] = useState(null);

  if (cart && entry === null) {
    setEntry(isBlocked(cart) ? 'blocked' : 'allowed');
  }

  if (entry === 'blocked') {
    return <Navigate to={paths.cart} replace />;
  }

  if (cartQuery.isError && !cart) {
    return (
      <PageFrame>
        <ErrorState
          title="Non è stato possibile caricare il carrello"
          message="Controlla la connessione e riprova."
          onRetry={() => cartQuery.refetch()}
        />
      </PageFrame>
    );
  }

  return (
    <PageFrame>
      {entry === 'allowed' ? <CheckoutContent cart={cart} /> : <CheckoutSkeleton />}
    </PageFrame>
  );
}
