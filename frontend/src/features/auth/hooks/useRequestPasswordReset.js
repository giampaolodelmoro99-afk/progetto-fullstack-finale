import { useMutation } from '@tanstack/react-query';
import { requestPasswordReset } from '@/features/auth/api/authApi';

export function useRequestPasswordReset() {
  return useMutation({ mutationFn: requestPasswordReset });
}
