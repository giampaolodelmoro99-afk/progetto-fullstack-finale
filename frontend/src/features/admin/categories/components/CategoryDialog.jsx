import { zodResolver } from '@hookform/resolvers/zod';
import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogTitle from '@mui/material/DialogTitle';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import { useId, useState } from 'react';
import { useForm } from 'react-hook-form';
import { FormTextField } from '@/components/form/FormTextField';
import { useSaveCategory } from '@/features/admin/categories/hooks/useSaveCategory';
import {
  categoryFormValues,
  categoryPayload,
  categorySchema,
} from '@/features/admin/categories/schemas';
import { useNotify } from '@/hooks/useNotify';
import { applyFieldErrors } from '@/utils/applyFieldErrors';

export function CategoryDialog({ category, onClose }) {
  const titleId = useId();
  const notify = useNotify();
  const saveCategory = useSaveCategory(category);
  const [formError, setFormError] = useState(null);
  const { control, handleSubmit, setError } = useForm({
    resolver: zodResolver(categorySchema),
    defaultValues: categoryFormValues(category),
  });

  function close() {
    if (!saveCategory.isPending) {
      onClose();
    }
  }

  function submit(values) {
    setFormError(null);
    saveCategory.mutate(categoryPayload(values), {
      onSuccess: () => {
        onClose();
        notify({ message: category ? 'Modifiche salvate' : 'Categoria creata' });
      },
      onError: (error) => {
        if (!applyFieldErrors(setError, error.fieldErrors, ['name', 'description'])) {
          setFormError(error.message);
        }
      },
    });
  }

  return (
    <Dialog open onClose={close} aria-labelledby={titleId} fullWidth maxWidth="sm">
      <DialogTitle id={titleId}>{category ? 'Modifica categoria' : 'Nuova categoria'}</DialogTitle>
      <form noValidate onSubmit={handleSubmit(submit)}>
        <DialogContent>
          <Stack sx={{ gap: 2.5, pt: 1 }}>
            {formError ? <Alert severity="error">{formError}</Alert> : null}
            <FormTextField
              name="name"
              control={control}
              label="Nome"
              required
              autoFocus
              fullWidth
              helperText={
                category
                  ? undefined
                  : "L'indirizzo della categoria si crea dal nome e non si potrà modificare."
              }
              slotProps={{ htmlInput: { maxLength: 50 } }}
            />
            {category ? (
              <TextField
                label="Slug"
                value={category.slug}
                fullWidth
                helperText={`Compare nell'indirizzo del catalogo: /products?category=${category.slug}. Non si può modificare.`}
                slotProps={{ htmlInput: { readOnly: true } }}
              />
            ) : null}
            <FormTextField
              name="description"
              control={control}
              label="Descrizione"
              multiline
              minRows={3}
              fullWidth
              slotProps={{ htmlInput: { maxLength: 255 } }}
            />
          </Stack>
        </DialogContent>
        <DialogActions>
          <Button onClick={close} disabled={saveCategory.isPending}>
            Annulla
          </Button>
          <Button type="submit" variant="contained" loading={saveCategory.isPending}>
            Salva
          </Button>
        </DialogActions>
      </form>
    </Dialog>
  );
}
