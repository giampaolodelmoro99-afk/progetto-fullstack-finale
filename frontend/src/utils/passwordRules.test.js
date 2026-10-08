import { describe, expect, it } from 'vitest';
import { newPasswordSchema, passwordRequirements } from '@/utils/passwordRules';

describe('passwordRules', () => {
  it('lists the requirements in the order shown to the user', () => {
    expect(passwordRequirements.map((requirement) => requirement.label)).toEqual([
      'Almeno 10 caratteri',
      'Lettere maiuscole e minuscole',
      'Almeno un numero',
    ]);
  });

  it('accepts a password that meets every requirement', () => {
    expect(newPasswordSchema.safeParse('Silicio2026').success).toBe(true);
  });

  it.each([
    ['Silicio20', 'La password deve avere almeno 10 caratteri.'],
    ['silicio2026', 'La password deve contenere lettere maiuscole e minuscole.'],
    ['SILICIO2026', 'La password deve contenere lettere maiuscole e minuscole.'],
    ['SilicioSilicio', 'La password deve contenere almeno un numero.'],
  ])('rejects %s', (password, message) => {
    const result = newPasswordSchema.safeParse(password);

    expect(result.success).toBe(false);
    expect(result.error.issues.map((issue) => issue.message)).toContain(message);
  });

  it('asks for a password when the field is empty', () => {
    const result = newPasswordSchema.safeParse('');

    expect(result.error.issues[0].message).toBe('Scegli una password.');
  });
});
