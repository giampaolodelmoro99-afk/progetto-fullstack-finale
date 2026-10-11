import { useMutation } from '@tanstack/react-query';
import { startTwoFactorSetup } from '@/features/security/api/securityApi';

export function useStartTwoFactorSetup() {
  return useMutation({ mutationFn: startTwoFactorSetup, gcTime: 0 });
}
