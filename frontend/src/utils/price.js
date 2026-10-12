export const EURO_PRICE_PATTERN = /^\d{1,4}(,\d{1,2})?$/;

export function parseEuroToCents(value) {
  const text = String(value ?? '').trim();

  if (!EURO_PRICE_PATTERN.test(text)) {
    return null;
  }

  const [euros, decimals = ''] = text.split(',');

  return Number(euros) * 100 + Number(decimals.padEnd(2, '0'));
}

export function formatCentsForInput(cents) {
  const euros = Math.floor(cents / 100);
  const decimals = String(cents % 100).padStart(2, '0');

  return `${euros},${decimals}`;
}
