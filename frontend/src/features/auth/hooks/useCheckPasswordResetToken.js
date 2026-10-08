import { useMutation } from '@tanstack/react-query';
import { checkPasswordResetToken } from '@/features/auth/api/authApi';

export function useCheckPasswordResetToken() {
  return useMutation({ mutationFn: checkPasswordResetToken, gcTime: 0 });
}
