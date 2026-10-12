import { z } from 'zod';

export const categorySchema = z.object({
  name: z
    .string()
    .trim()
    .min(1, 'Inserisci il nome della categoria.')
    .max(50, 'Usa al massimo 50 caratteri.'),
  description: z.string().trim().max(255, 'Usa al massimo 255 caratteri.'),
});

export function categoryFormValues(category) {
  return {
    name: category?.name ?? '',
    description: category?.description ?? '',
  };
}

export function categoryPayload(values) {
  return {
    name: values.name,
    description: values.description === '' ? null : values.description,
  };
}
