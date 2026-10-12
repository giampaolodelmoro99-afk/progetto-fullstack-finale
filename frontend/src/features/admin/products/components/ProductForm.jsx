import { zodResolver } from '@hookform/resolvers/zod';
import Alert from '@mui/material/Alert';
import AlertTitle from '@mui/material/AlertTitle';
import Grid from '@mui/material/Grid';
import Stack from '@mui/material/Stack';
import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { useNavigate } from 'react-router';
import { paths } from '@/app/router/paths';
import { useAdminCategories } from '@/features/admin/categories/hooks/useAdminCategories';
import { UnsavedChangesDialog } from '@/features/admin/components/UnsavedChangesDialog';
import { useUnsavedChangesGuard } from '@/features/admin/hooks/useUnsavedChangesGuard';
import { PricingCard } from '@/features/admin/products/components/PricingCard';
import { ProductImagesCard } from '@/features/admin/products/components/ProductImagesCard';
import { ProductInfoCard } from '@/features/admin/products/components/ProductInfoCard';
import { SaveBar } from '@/features/admin/products/components/SaveBar';
import { SpecificationsCard } from '@/features/admin/products/components/SpecificationsCard';
import { useSaveProduct } from '@/features/admin/products/hooks/useSaveProduct';
import {
  countFieldErrors,
  productFieldNames,
  productFormValues,
  productPayload,
  productSchema,
} from '@/features/admin/products/schemas';
import { useNotify } from '@/hooks/useNotify';

function errorSummary(count) {
  return count === 1
    ? 'Correggi il campo evidenziato e salva di nuovo.'
    : `Correggi i ${count} campi evidenziati e salva di nuovo.`;
}

function categoryOptions(categoriesQuery, product) {
  if (categoriesQuery.data) {
    return categoriesQuery.data;
  }

  return product?.category ? [product.category] : [];
}

export function ProductForm({ product }) {
  const isNew = product === null;
  const productId = isNew ? null : product.id;
  const navigate = useNavigate();
  const notify = useNotify();
  const categoriesQuery = useAdminCategories();
  const saveProduct = useSaveProduct(productId);
  const [submitFailed, setSubmitFailed] = useState(false);
  const [formError, setFormError] = useState(null);
  const form = useForm({
    resolver: zodResolver(productSchema),
    defaultValues: productFormValues(product),
  });
  const { control, handleSubmit, setError, reset, formState } = form;
  const { isDirty, isSubmitting, errors } = formState;
  const guard = useUnsavedChangesGuard(isDirty && !isSubmitting);
  const errorCount = countFieldErrors(errors);

  function showServerErrors(error) {
    const fields = productFieldNames(error.fieldErrors);
    fields.forEach((field, index) => {
      setError(
        field,
        { type: 'server', message: error.fieldErrors[field] },
        { shouldFocus: index === 0 },
      );
    });

    if (fields.length > 0) {
      setSubmitFailed(true);
    }

    if (fields.length < Object.keys(error.fieldErrors ?? {}).length || fields.length === 0) {
      setFormError(error.message);
    }
  }

  async function submit(values) {
    setFormError(null);

    try {
      const saved = await saveProduct.mutateAsync(productPayload(values, { withImages: !isNew }));
      setSubmitFailed(false);
      reset(productFormValues(saved));

      if (isNew) {
        guard.allowNextNavigation();
        notify({ message: 'Prodotto creato' });
        navigate(paths.adminProductEdit(saved.id), { replace: true });
      } else {
        notify({ message: 'Modifiche salvate' });
      }
    } catch (error) {
      showServerErrors(error);
    }
  }

  function cancel() {
    if (isNew) {
      navigate(paths.adminProducts);
    } else {
      reset();
    }
  }

  return (
    <Stack
      component="form"
      noValidate
      aria-label={isNew ? 'Nuovo prodotto' : 'Modifica prodotto'}
      onSubmit={handleSubmit(submit, () => setSubmitFailed(true))}
      sx={{ gap: 3 }}
    >
      {submitFailed && errorCount > 0 ? (
        <Alert severity="error">
          <AlertTitle>Il prodotto non è stato salvato</AlertTitle>
          {errorSummary(errorCount)}
        </Alert>
      ) : null}
      {formError ? <Alert severity="error">{formError}</Alert> : null}
      <Grid container spacing={3} sx={{ alignItems: 'flex-start' }}>
        <Grid size={{ xs: 12, md: 8 }}>
          <Stack sx={{ gap: 3 }}>
            <ProductInfoCard
              control={control}
              categories={categoryOptions(categoriesQuery, product)}
            />
            <SpecificationsCard control={control} />
            <ProductImagesCard productId={productId} form={form} />
          </Stack>
        </Grid>
        <Grid size={{ xs: 12, md: 4 }}>
          <PricingCard control={control} />
        </Grid>
      </Grid>
      <SaveBar
        isDirty={isDirty}
        saving={isSubmitting}
        cancelDisabled={!isNew && !isDirty}
        onCancel={cancel}
      />
      {guard.isBlocked ? <UnsavedChangesDialog onStay={guard.stay} onLeave={guard.leave} /> : null}
    </Stack>
  );
}
