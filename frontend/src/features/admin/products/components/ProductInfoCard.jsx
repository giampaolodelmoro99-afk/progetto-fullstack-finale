import Grid from '@mui/material/Grid';
import MenuItem from '@mui/material/MenuItem';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import { Controller } from 'react-hook-form';
import { FormTextField } from '@/components/form/FormTextField';
import { AdminSectionCard } from '@/features/admin/components/AdminSectionCard';
import { SHORT_DESCRIPTION_MAX_LENGTH } from '@/features/admin/products/schemas';

function HelperWithCounter({ text, count }) {
  return (
    <Stack component="span" direction="row" sx={{ justifyContent: 'space-between', gap: 2 }}>
      <span>{text}</span>
      <span aria-hidden="true">
        {count}/{SHORT_DESCRIPTION_MAX_LENGTH}
      </span>
    </Stack>
  );
}

export function ProductInfoCard({ control, categories }) {
  return (
    <AdminSectionCard title="Informazioni">
      <Grid container spacing={2.5}>
        <Grid size={12}>
          <FormTextField name="name" control={control} label="Nome" required fullWidth />
        </Grid>
        <Grid size={{ xs: 12, md: 6 }}>
          <FormTextField
            name="sku"
            control={control}
            label="SKU"
            required
            fullWidth
            helperText="Lettere maiuscole, numeri e trattini"
            slotProps={{ htmlInput: { maxLength: 64, autoCapitalize: 'characters' } }}
          />
        </Grid>
        <Grid size={{ xs: 12, md: 6 }}>
          <Controller
            name="category_id"
            control={control}
            render={({ field: { ref, ...field }, fieldState }) => (
              <TextField
                {...field}
                inputRef={ref}
                select
                label="Categoria"
                required
                fullWidth
                error={Boolean(fieldState.error)}
                helperText={fieldState.error?.message}
              >
                {categories.map((category) => (
                  <MenuItem key={category.id} value={String(category.id)}>
                    {category.name}
                  </MenuItem>
                ))}
              </TextField>
            )}
          />
        </Grid>
        <Grid size={12}>
          <Controller
            name="short_description"
            control={control}
            render={({ field: { ref, ...field }, fieldState }) => (
              <TextField
                {...field}
                inputRef={ref}
                label="Descrizione breve"
                required
                fullWidth
                error={Boolean(fieldState.error)}
                helperText={
                  <HelperWithCounter
                    text={
                      fieldState.error?.message ?? 'Compare sotto il nome nella scheda prodotto.'
                    }
                    count={field.value.length}
                  />
                }
                slotProps={{ formHelperText: { component: 'div' } }}
              />
            )}
          />
        </Grid>
        <Grid size={12}>
          <FormTextField
            name="description"
            control={control}
            label="Descrizione lunga"
            multiline
            minRows={5}
            fullWidth
          />
        </Grid>
      </Grid>
    </AdminSectionCard>
  );
}
