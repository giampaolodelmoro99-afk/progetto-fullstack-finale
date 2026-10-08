export const SECOND_FACTOR_METHODS = {
  totp: 'totp',
  recoveryCode: 'recovery_code',
  passkey: 'passkey',
};

export const NO_SECOND_FACTOR = 'none';

const CODE_MESSAGES = {
  [SECOND_FACTOR_METHODS.totp]: 'Inserisci il codice a 6 cifre.',
  [SECOND_FACTOR_METHODS.recoveryCode]: 'Inserisci un codice di recupero.',
};

export function requireSecondFactorCode(values, context) {
  const message = CODE_MESSAGES[values.method];

  if (message && values.code.trim() === '') {
    context.addIssue({ code: 'custom', path: ['code'], message });
  }
}

export function secondFactorPayload({ method, code }, credential = null) {
  if (method === SECOND_FACTOR_METHODS.passkey) {
    return { method, credential };
  }

  if (CODE_MESSAGES[method]) {
    return { method, code: code.trim() };
  }

  return {};
}

export function recoveryCodesNotice(meta) {
  if (!meta?.recovery_codes_low) {
    return null;
  }

  const remaining = meta.recovery_codes_remaining;

  if (remaining === 0) {
    return 'Hai usato tutti i codici di recupero: generane di nuovi dalla pagina Sicurezza.';
  }

  return remaining === 1
    ? 'Ti resta 1 codice di recupero: generane di nuovi dalla pagina Sicurezza.'
    : `Ti restano ${remaining} codici di recupero: generane di nuovi dalla pagina Sicurezza.`;
}
