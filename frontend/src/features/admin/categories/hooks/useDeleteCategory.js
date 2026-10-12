import { useMutation, useQueryClient } from '@tanstack/react-query';
import { deleteCategory } from '@/features/admin/categories/api/categoriesApi';
import { invalidateCategories } from '@/features/admin/categories/hooks/invalidateCategories';

export function useDeleteCategory() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (category) => deleteCategory(category.id),
    onSuccess: () => invalidateCategories(queryClient),
    onError: (error) => {
      if (error.code === 'category_has_products') {
        return invalidateCategories(queryClient);
      }

      return undefined;
    },
  });
}
