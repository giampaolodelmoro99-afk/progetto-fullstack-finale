import LinearProgress from '@mui/material/LinearProgress';
import Stack from '@mui/material/Stack';
import Step from '@mui/material/Step';
import StepLabel from '@mui/material/StepLabel';
import Stepper from '@mui/material/Stepper';
import Typography from '@mui/material/Typography';
import { VisuallyHidden } from '@/components/feedback/VisuallyHidden';
import { CHECKOUT_STEPS } from '@/features/checkout/checkoutReducer';

export function CheckoutProgress({ step, isDesktop }) {
  if (isDesktop) {
    return (
      <Stepper activeStep={step} aria-label="Passi del checkout">
        {CHECKOUT_STEPS.map((label, index) => (
          <Step key={label} completed={index < step}>
            <StepLabel>
              {label}
              {index < step ? <VisuallyHidden>, completato</VisuallyHidden> : null}
            </StepLabel>
          </Step>
        ))}
      </Stepper>
    );
  }

  const next = CHECKOUT_STEPS[step + 1];

  return (
    <Stack sx={{ gap: 1 }}>
      <Stack direction="row" sx={{ justifyContent: 'space-between', alignItems: 'baseline' }}>
        <Typography variant="subtitle1" component="p">
          {CHECKOUT_STEPS[step]}
        </Typography>
        <Typography variant="caption" sx={{ color: 'text.secondary' }}>
          Passo {step + 1} di {CHECKOUT_STEPS.length}
        </Typography>
      </Stack>
      <LinearProgress
        variant="determinate"
        value={((step + 1) / CHECKOUT_STEPS.length) * 100}
        aria-label={`Passo ${step + 1} di ${CHECKOUT_STEPS.length}`}
      />
      {next ? (
        <Typography variant="caption" sx={{ color: 'text.secondary' }}>
          Poi: {next}
        </Typography>
      ) : null}
    </Stack>
  );
}
