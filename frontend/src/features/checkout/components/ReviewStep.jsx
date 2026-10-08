import ArrowBack from '@mui/icons-material/ArrowBack';
import CreditCardOutlined from '@mui/icons-material/CreditCardOutlined';
import LockOutlined from '@mui/icons-material/LockOutlined';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Checkbox from '@mui/material/Checkbox';
import Divider from '@mui/material/Divider';
import FormControl from '@mui/material/FormControl';
import FormControlLabel from '@mui/material/FormControlLabel';
import FormHelperText from '@mui/material/FormHelperText';
import Link from '@mui/material/Link';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';
import { OrderTotals } from '@/components/data-display/OrderTotals';
import { STEP } from '@/features/checkout/checkoutReducer';
import { CheckoutItemList } from '@/features/checkout/components/CheckoutItemList';
import { digitsOnly } from '@/features/checkout/schemas';
import { legalSections } from '@/features/info/sections';
import { formatPostalLine, formatPrice } from '@/utils/format';

export const REVIEW_FORM_ID = 'checkout-review-form';

function ReviewSection({ title, onEdit, editLabel, children }) {
  return (
    <Stack sx={{ gap: 1 }}>
      <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'baseline' }}>
        <Typography variant="subtitle2" component="h3">
          {title}
        </Typography>
        {onEdit ? (
          <Link
            component="button"
            type="button"
            variant="body2"
            onClick={onEdit}
            aria-label={editLabel}
          >
            Modifica
          </Link>
        ) : null}
      </Stack>
      {children}
    </Stack>
  );
}

export function ReviewStep({
  cart,
  address,
  payment,
  termsAccepted,
  termsError,
  canPay,
  isPaying,
  primaryInline,
  onTermsChange,
  onEdit,
  onPay,
}) {
  const lastFour = digitsOnly(payment.number).slice(-4);

  function handleSubmit(event) {
    event.preventDefault();

    if (canPay) {
      onPay();
    }
  }

  return (
    <Card component="section" aria-labelledby="review-step-title">
      <CardContent>
        <Stack
          component="form"
          id={REVIEW_FORM_ID}
          noValidate
          onSubmit={handleSubmit}
          sx={{ gap: 3 }}
        >
          <Typography id="review-step-title" variant="h4" component="h2" tabIndex={-1}>
            Controlla e paga
          </Typography>
          <ReviewSection
            title="Indirizzo di spedizione"
            editLabel="Modifica l'indirizzo di spedizione"
            onEdit={() => onEdit(STEP.address)}
          >
            {address ? (
              <Stack>
                <Typography variant="body2">{address.full_name}</Typography>
                <Typography variant="body2">
                  {address.street}, {formatPostalLine(address)}
                </Typography>
                <Typography variant="body2">{address.phone}</Typography>
              </Stack>
            ) : null}
            <Typography variant="caption" sx={{ color: 'text.secondary' }}>
              Spedizione standard, consegna in 24/48 ore
            </Typography>
          </ReviewSection>
          <Divider />
          <ReviewSection
            title="Pagamento"
            editLabel="Modifica i dati della carta"
            onEdit={() => onEdit(STEP.payment)}
          >
            <Stack direction="row" sx={{ gap: 1, alignItems: 'center' }}>
              <CreditCardOutlined fontSize="small" />
              <Typography variant="body2">Carta che termina con {lastFour}</Typography>
            </Stack>
            <Typography variant="caption" sx={{ color: 'text.secondary' }}>
              {payment.holder} · scadenza {payment.expiry}
            </Typography>
          </ReviewSection>
          <Divider />
          <ReviewSection title="Prodotti">
            <CheckoutItemList items={cart.items} detailed label="Prodotti da pagare" />
          </ReviewSection>
          <Divider />
          <OrderTotals
            subtotal={cart.summary.subtotal}
            shippingCost={cart.summary.shipping_cost}
            total={cart.summary.total}
            compactShipping
          />
          <FormControl error={Boolean(termsError)}>
            <FormControlLabel
              control={
                <Checkbox
                  checked={termsAccepted}
                  onChange={(event) => onTermsChange(event.target.checked)}
                />
              }
              label={
                <>
                  Ho letto e accetto le{' '}
                  <Link
                    component={RouterLink}
                    to={paths.legalSection(legalSections.termsOfSale)}
                    target="_blank"
                    aria-label="condizioni di vendita (si apre in una nuova scheda)"
                  >
                    condizioni di vendita
                  </Link>
                </>
              }
            />
            {termsError ? <FormHelperText>{termsError}</FormHelperText> : null}
          </FormControl>
          <Stack direction="row" sx={{ justifyContent: 'space-between', gap: 2 }}>
            <Button startIcon={<ArrowBack />} color="inherit" onClick={() => onEdit(STEP.payment)}>
              Indietro
            </Button>
            {primaryInline ? (
              <Button
                type="submit"
                variant="contained"
                size="large"
                startIcon={<LockOutlined />}
                disabled={!canPay}
                loading={isPaying}
              >
                Paga {formatPrice(cart.summary.total)}
              </Button>
            ) : null}
          </Stack>
        </Stack>
      </CardContent>
    </Card>
  );
}
