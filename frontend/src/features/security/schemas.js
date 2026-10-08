import { z } from 'zod';
import { requireSecondFactorCode } from '@/features/security/utils/secondFactor';

export const confirmIdentitySchema = z
  .object({
    password: z.string().min(1, 'Inserisci la password.'),
    method: z.string(),
    code: z.string(),
  })
  .superRefine(requireSecondFactorCode);
