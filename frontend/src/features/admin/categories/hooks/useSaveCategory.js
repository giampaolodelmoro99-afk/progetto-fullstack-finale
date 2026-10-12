import { useMutation, useQueryClient } from '@tanstack/react-query';
import { createCategory, updateCategory } from '@/features/admin/categories/api/categoriesApi';
import { invalidateCategories } from '@/features/admin/categories/hooks/invalidateCategories';

export function useSaveCategory(category) {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (values) =>
      category ? updateCategory({ id: category.id, ...values }) : createCategory(values),
    onSuccess: () => invalidateCategories(queryClient),
  });
}
