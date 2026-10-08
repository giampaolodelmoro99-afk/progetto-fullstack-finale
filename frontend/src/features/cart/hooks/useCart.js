import { useQuery } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { fetchCart } from '@/features/cart/api/cartApi';

export function useCart({ enabled = true } = {}) {
  return useQuery({
    queryKey: queryKeys.cart,
    queryFn: fetchCart,
    enabled,
  });
}
