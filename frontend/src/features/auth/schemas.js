import { z } from 'zod';
import { requireSecondFactorCode } from '@/features/security/utils/secondFactor';
import { newPasswordSchema } from '@/utils/passwordRules';

const emailSchema = z
  .string()
  .trim()
  .min(1, "Inserisci l'indirizzo email.")
  .pipe(z.email('Inserisci un indirizzo email valido.'));

const PASSWORDS_DO_NOT_MATCH = 'Le password non coincidono.';

function requireMatchingPasswords(values, context) {
  if (values.password !== values.password_confirmation) {
    context.addIssue({
      code: 'custom',
      path: ['password_confirmation'],
      message: PASSWORDS_DO_NOT_MATCH,
    });
  }
}

export const loginSchema = z.object({
  email: emailSchema,
  password: z.string().min(1, 'Inserisci la password.'),
});

export const twoFactorSchema = z
  .object({
    method: z.string(),
    code: z.string(),
  })
  .superRefine(requireSecondFactorCode);

export const registerSchema = z
  .object({
    name: z
      .string()
      .trim()
      .min(1, 'Inserisci nome e cognome.')
      .max(255, 'Il nome può avere al massimo 255 caratteri.'),
    email: emailSchema,
    password: newPasswordSchema,
    password_confirmation: z.string().min(1, 'Ripeti la password.'),
  })
  .superRefine(requireMatchingPasswords);

export const forgotPasswordSchema = z.object({
  email: emailSchema,
});

export const resetPasswordSchema = z
  .object({
    password: newPasswordSchema,
    password_confirmation: z.string().min(1, 'Ripeti la nuova password.'),
    method: z.string(),
    code: z.string(),
  })
  .superRefine(requireMatchingPasswords)
  .superRefine(requireSecondFactorCode);
