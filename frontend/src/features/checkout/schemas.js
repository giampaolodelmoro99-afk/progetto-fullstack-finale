import { z } from 'zod';
import { passesLuhn } from '@/features/checkout/utils/luhn';

const EXPIRY_PATTERN = /^(0[1-9]|1[0-2])\/(\d{2})$/;

export function digitsOnly(value) {
  return value.replace(/\s+/g, '');
}

function hasCardLength(value) {
  return /^\d{13,19}$/.test(digitsOnly(value));
}

export function isExpiryInFuture(expiry, today = new Date()) {
  const match = EXPIRY_PATTERN.exec(expiry);

  if (!match) {
    return false;
  }

  const month = Number(match[1]);
  const year = 2000 + Number(match[2]);
  const currentYear = today.getFullYear();
  const currentMonth = today.getMonth() + 1;

  return year > currentYear || (year === currentYear && month >= currentMonth);
}

export const paymentSchema = z.object({
  holder: z
    .string()
    .trim()
    .min(1, "Inserisci l'intestatario della carta.")
    .max(255, 'Usa al massimo 255 caratteri.'),
  number: z
    .string()
    .refine((value) => hasCardLength(value), {
      message: 'Il numero della carta deve avere da 13 a 19 cifre.',
    })
    .refine((value) => !hasCardLength(value) || passesLuhn(digitsOnly(value)), {
      message: 'Controlla il numero della carta.',
    }),
  expiry: z
    .string()
    .trim()
    .regex(EXPIRY_PATTERN, 'Usa il formato MM/AA, per esempio 08/29.')
    .refine((value) => !EXPIRY_PATTERN.test(value) || isExpiryInFuture(value), {
      message: 'La carta è scaduta.',
    }),
  cvc: z
    .string()
    .trim()
    .regex(/^\d{3}$/, 'Il CVC deve avere 3 cifre.'),
});

export const emptyPayment = { holder: '', number: '', expiry: '', cvc: '' };
