import { useMutation, useQueryClient } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { deleteProductImage, uploadProductImages } from '@/features/admin/products/api/productsApi';
import { invalidateProducts } from '@/features/admin/products/hooks/invalidateProducts';

export const MAX_FILES_PER_REQUEST = 10;

function chunk(files) {
  const groups = [];

  for (let index = 0; index < files.length; index += MAX_FILES_PER_REQUEST) {
    groups.push(files.slice(index, index + MAX_FILES_PER_REQUEST));
  }

  return groups;
}

function replaceCachedImages(queryClient, productId, update) {
  queryClient.setQueryData(queryKeys.admin.products.detail(String(productId)), (product) =>
    product ? { ...product, images: update(product.images) } : product,
  );
}

export function useUploadProductImages(productId) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async ({ files, onUploaded }) => {
      for (const group of chunk(files)) {
        const images = await uploadProductImages(productId, group);
        replaceCachedImages(queryClient, productId, () => images);
        onUploaded(images);
      }
    },
    onSettled: () => invalidateProducts(queryClient, { exceptDetail: String(productId) }),
  });
}

export function useDeleteProductImage(productId) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (image) => deleteProductImage(productId, image.id),
    onSuccess: (response, image) => {
      replaceCachedImages(queryClient, productId, (images) =>
        images.filter((item) => item.id !== image.id),
      );

      return invalidateProducts(queryClient, { exceptDetail: String(productId) });
    },
  });
}
