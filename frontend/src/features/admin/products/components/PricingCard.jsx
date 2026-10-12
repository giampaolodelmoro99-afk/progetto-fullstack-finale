import Divider from '@mui/material/Divider';
import FormControlLabel from '@mui/material/FormControlLabel';
import FormHelperText from '@mui/material/FormHelperText';
import InputAdornment from '@mui/material/InputAdornment';
import Stack from '@mui/material/Stack';
import Switch from '@mui/material/Switch';
import { useId } from 'react';
import { Controller } from 'react-hook-form';
import { FormTextField } from '@/components/form/FormTextField';
import { AdminSectionCard } from '@/features/admin/components/AdminSectionCard';
import { InlineAmountSkeleton } from '@/features/settings/components/InlineAmountSkeleton';
import { useAdminSettings } from '@/features/settings/hooks/useAdminSettings';

function stockHelper(settingsQuery) {
  if (settingsQuery.isPending) {
    return <InlineAmountSkeleton />;
  }

  if (settingsQuery.isError) {
    return undefined;
  }

  return `Con ${settingsQuery.data.low_stock_threshold} pezzi o meno il prodotto entra tra le scorte basse.`;
}

export function PricingCard({ control }) {
  const settingsQuery = useAdminSettings();
  const visibilityHelperId = useId();

  return (
    <AdminSectionCard title="Prezzo e disponibilità">
      <Stack sx={{ gap: 2.5 }}>
        <FormTextField
          name="price"
          control={control}
          label="Prezzo"
          required
          fullWidth
          helperText="IVA inclusa"
          slotProps={{
            htmlInput: { inputMode: 'decimal' },
            input: { endAdornment: <InputAdornment position="end">€</InputAdornment> },
          }}
        />
        <FormTextField
          name="stock"
          control={control}
          label="Stock"
          required
          fullWidth
          helperText={stockHelper(settingsQuery)}
          slotProps={{
            htmlInput: { inputMode: 'numeric' },
            input: { endAdornment: <InputAdornment position="end">pezzi</InputAdornment> },
            formHelperText: { component: 'div' },
          }}
        />
        <Divider />
        <Controller
          name="is_active"
          control={control}
          render={({ field: { value, onChange, ref, ...field } }) => (
            <Stack sx={{ gap: 0.5 }}>
              <FormControlLabel
                labelPlacement="start"
                label="Visibile nel negozio"
                sx={{ mx: 0, justifyContent: 'space-between' }}
                control={
                  <Switch
                    {...field}
                    checked={value}
                    onChange={(event) => onChange(event.target.checked)}
                    slotProps={{
                      input: { ref, 'aria-describedby': visibilityHelperId },
                    }}
                  />
                }
              />
              <FormHelperText id={visibilityHelperId} sx={{ mx: 0 }}>
                {value
                  ? 'Il prodotto compare nel negozio e si può acquistare.'
                  : 'Il prodotto resta in archivio e non compare nel negozio.'}
              </FormHelperText>
            </Stack>
          )}
        />
      </Stack>
    </AdminSectionCard>
  );
}
