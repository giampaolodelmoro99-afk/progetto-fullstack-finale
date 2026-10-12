import { ConfirmDialog } from '@/features/admin/components/ConfirmDialog';
import { useDeleteProduct } from '@/features/admin/products/hooks/useDeleteProduct';
import { useNotify } from '@/hooks/useNotify';

export function DeleteProductDialog({ product, onClose }) {
  const deleteProduct = useDeleteProduct();
  const notify = useNotify();

  function confirm() {
    deleteProduct.mutate(product, {
      onSuccess: () => {
        onClose();
        notify({ message: 'Prodotto eliminato' });
      },
    });
  }

  return (
    <ConfirmDialog
      title={`Eliminare «${product.name}»?`}
      description="Il prodotto sparisce dal catalogo e dal negozio. Gli ordini già ricevuti che lo contengono restano invariati. L'operazione non si può annullare."
      cancelLabel="Mantieni il prodotto"
      confirmLabel="Elimina il prodotto"
      onConfirm={confirm}
      onClose={onClose}
      pending={deleteProduct.isPending}
      error={deleteProduct.isError ? deleteProduct.error.message : null}
    />
  );
}
