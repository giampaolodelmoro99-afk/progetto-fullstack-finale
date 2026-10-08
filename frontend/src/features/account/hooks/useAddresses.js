import { useQuery } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { fetchAddresses } from '@/features/account/api/addressesApi';

export function useAddresses() {
  return useQuery({
    queryKey: queryKeys.addresses,
    queryFn: fetchAddresses,
  });
}
