import { z } from 'zod';
import { requireSecondFactorCode } from '@/features/security/utils/secondFactor';
import { newPasswordSchema } from '@/utils/passwordRules';

export const confirmIdentitySchema = z
  .object({
    password: z.string().min(1, 'Inserisci la password.'),
    method: z.string(),
    code: z.string(),
  })
  .superRefine(requireSecondFactorCode);

export const changePasswordSchema = z
  .object({
    current_password: z.string().min(1, 'Inserisci la password attuale.'),
    password: newPasswordSchema,
    password_confirmation: z.string().min(1, 'Ripeti la nuova password.'),
    method: z.string(),
    code: z.string(),
  })
  .superRefine((values, context) => {
    if (values.password !== values.password_confirmation) {
      context.addIssue({
        code: 'custom',
        path: ['password_confirmation'],
        message: 'Le password non coincidono.',
      });
    }
  })
  .superRefine(requireSecondFactorCode);

export const totpCodeSchema = z.object({
  code: z
    .string()
    .trim()
    .regex(/^\d{6}$/, 'Inserisci il codice a 6 cifre.'),
});

export const passkeyNameSchema = z.object({
  name: z
    .string()
    .trim()
    .min(1, 'Dai un nome alla passkey.')
    .max(255, 'Il nome può avere al massimo 255 caratteri.'),
});
