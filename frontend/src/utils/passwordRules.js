import { z } from 'zod';

export const passwordRequirements = [
  {
    id: 'length',
    label: 'Almeno 10 caratteri',
    message: 'La password deve avere almeno 10 caratteri.',
    test: (value) => value.length >= 10,
  },
  {
    id: 'mixed-case',
    label: 'Lettere maiuscole e minuscole',
    message: 'La password deve contenere lettere maiuscole e minuscole.',
    test: (value) => /\p{Lu}/u.test(value) && /\p{Ll}/u.test(value),
  },
  {
    id: 'number',
    label: 'Almeno un numero',
    message: 'La password deve contenere almeno un numero.',
    test: (value) => /\p{N}/u.test(value),
  },
];

export const newPasswordSchema = passwordRequirements.reduce(
  (schema, requirement) => schema.refine(requirement.test, { message: requirement.message }),
  z.string().min(1, 'Scegli una password.'),
);
