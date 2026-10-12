import { useMutation, useQueryClient } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { createProduct, updateProduct } from '@/features/admin/products/api/productsApi';
import { invalidateProducts } from '@/features/admin/products/hooks/invalidateProducts';

export function useSaveProduct(productId) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (payload) =>
      productId === null ? createProduct(payload) : updateProduct(productId, payload),
    onSuccess: (product) => {
      queryClient.setQueryData(queryKeys.admin.products.detail(String(product.id)), product);

      return invalidateProducts(queryClient, { exceptDetail: String(product.id) });
    },
  });
}
