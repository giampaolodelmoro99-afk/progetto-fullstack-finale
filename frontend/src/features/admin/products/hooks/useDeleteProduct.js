import { useMutation, useQueryClient } from '@tanstack/react-query';
import { deleteProduct } from '@/features/admin/products/api/productsApi';
import { invalidateProducts } from '@/features/admin/products/hooks/invalidateProducts';

export function useDeleteProduct() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (product) => deleteProduct(product.id),
    onSuccess: () => invalidateProducts(queryClient),
  });
}
