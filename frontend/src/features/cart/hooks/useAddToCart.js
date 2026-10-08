import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router';
import { queryKeys } from '@/api/queryKeys';
import { paths } from '@/app/router/paths';
import { addCartItem } from '@/features/cart/api/cartApi';
import { useNotify } from '@/hooks/useNotify';

export function useAddToCart() {
  const queryClient = useQueryClient();
  const notify = useNotify();
  const navigate = useNavigate();

  return useMutation({
    mutationFn: addCartItem,
    onSuccess: (cart) => {
      queryClient.setQueryData(queryKeys.cart, cart);
      notify({
        message: 'Aggiunto al carrello',
        action: { label: 'Vai al carrello', onClick: () => navigate(paths.cart) },
      });
    },
    onError: (error) =>
      notify({
        message: Object.values(error.fieldErrors ?? {})[0] ?? error.message,
        severity: 'error',
      }),
  });
}
