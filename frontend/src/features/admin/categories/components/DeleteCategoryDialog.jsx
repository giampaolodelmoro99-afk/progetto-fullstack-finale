import { ConfirmDialog } from '@/features/admin/components/ConfirmDialog';
import { useDeleteCategory } from '@/features/admin/categories/hooks/useDeleteCategory';
import { useNotify } from '@/hooks/useNotify';

export function DeleteCategoryDialog({ category, onClose }) {
  const deleteCategory = useDeleteCategory();
  const notify = useNotify();

  function confirm() {
    deleteCategory.mutate(category, {
      onSuccess: () => {
        onClose();
        notify({ message: 'Categoria eliminata' });
      },
    });
  }

  return (
    <ConfirmDialog
      title={`Eliminare la categoria «${category.name}»?`}
      description="La categoria non contiene prodotti. Sparisce dal menu del negozio e dai filtri del catalogo. L'operazione non si può annullare."
      cancelLabel="Mantieni la categoria"
      confirmLabel="Elimina la categoria"
      onConfirm={confirm}
      onClose={onClose}
      pending={deleteCategory.isPending}
      error={deleteCategory.isError ? deleteCategory.error.message : null}
    />
  );
}
