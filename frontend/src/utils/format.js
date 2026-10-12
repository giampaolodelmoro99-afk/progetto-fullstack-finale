const TIME_ZONE = 'Europe/Rome';

const priceFormatter = new Intl.NumberFormat('it-IT', {
  style: 'currency',
  currency: 'EUR',
  useGrouping: 'always',
});

const dateFormatter = new Intl.DateTimeFormat('it-IT', {
  day: 'numeric',
  month: 'long',
  year: 'numeric',
  timeZone: TIME_ZONE,
});

const timeFormatter = new Intl.DateTimeFormat('it-IT', {
  hour: '2-digit',
  minute: '2-digit',
  timeZone: TIME_ZONE,
});

const shortDateFormatter = new Intl.DateTimeFormat('it-IT', {
  day: 'numeric',
  month: 'short',
  year: 'numeric',
  timeZone: TIME_ZONE,
});

const shortDayFormatter = new Intl.DateTimeFormat('it-IT', {
  day: 'numeric',
  month: 'short',
  timeZone: TIME_ZONE,
});

const monthYearFormatter = new Intl.DateTimeFormat('it-IT', {
  month: 'long',
  year: 'numeric',
  timeZone: 'UTC',
});

function toDate(value) {
  return value instanceof Date ? value : new Date(value);
}

export function formatPrice(cents) {
  return priceFormatter.format(cents / 100);
}

export function formatDate(value) {
  return dateFormatter.format(toDate(value));
}

export function formatTime(value) {
  return timeFormatter.format(toDate(value));
}

export function formatDateTime(value) {
  return `${formatDate(value)}, ${formatTime(value)}`;
}

export function formatDateTimeShort(value) {
  return `${shortDateFormatter.format(toDate(value))}, ${formatTime(value)}`;
}

export function formatDayTimeShort(value) {
  return `${shortDayFormatter.format(toDate(value))}, ${formatTime(value)}`;
}

export function formatMonthYear(month) {
  const [year, monthNumber] = String(month).split('-').map(Number);
  const text = monthYearFormatter.format(new Date(Date.UTC(year, monthNumber - 1, 1)));

  return text.charAt(0).toUpperCase() + text.slice(1);
}

export function formatItemCount(count) {
  return `${count} ${count === 1 ? 'articolo' : 'articoli'}`;
}

export function formatOrderCount(count) {
  return `${count} ${count === 1 ? 'ordine' : 'ordini'}`;
}

export function formatPostalLine(address) {
  return `${address.postal_code} ${address.city} (${address.province}), Italia`;
}
