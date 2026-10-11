import { z } from 'zod';

export const addressSchema = z.object({
  full_name: z
    .string()
    .trim()
    .min(1, 'Inserisci nome e cognome.')
    .max(255, 'Usa al massimo 255 caratteri.'),
  street: z
    .string()
    .trim()
    .min(1, 'Inserisci via e numero civico.')
    .max(255, 'Usa al massimo 255 caratteri.'),
  city: z.string().trim().min(1, 'Inserisci la città.').max(100, 'Usa al massimo 100 caratteri.'),
  postal_code: z
    .string()
    .trim()
    .regex(/^\d{5}$/, 'Il CAP deve avere 5 cifre'),
  province: z
    .string()
    .trim()
    .toUpperCase()
    .regex(/^[A-Z]{2}$/, 'Usa la sigla di due lettere della provincia, per esempio PI.'),
  phone: z
    .string()
    .trim()
    .min(1, 'Inserisci il numero di telefono.')
    .max(20, 'Usa al massimo 20 caratteri.')
    .regex(/^\+?[0-9 ]+$/, 'Usa solo cifre e spazi, con un + iniziale facoltativo.'),
  is_default: z.boolean(),
});

export const emptyAddress = {
  full_name: '',
  street: '',
  city: '',
  postal_code: '',
  province: '',
  phone: '',
  is_default: false,
};

export const profileSchema = z.object({
  name: z
    .string()
    .trim()
    .min(1, 'Inserisci nome e cognome.')
    .max(255, 'Usa al massimo 255 caratteri.'),
});

export const newEmailSchema = z.object({
  email: z
    .string()
    .trim()
    .min(1, "Inserisci l'indirizzo email.")
    .max(255, 'Usa al massimo 255 caratteri.')
    .pipe(z.email('Inserisci un indirizzo email valido.')),
});
