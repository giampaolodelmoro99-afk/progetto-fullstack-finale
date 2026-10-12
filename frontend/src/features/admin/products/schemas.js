import { z } from 'zod';
import { EURO_PRICE_PATTERN, formatCentsForInput, parseEuroToCents } from '@/utils/price';

export const SHORT_DESCRIPTION_MAX_LENGTH = 160;

export const MAX_SPECIFICATIONS = 20;

const PRICE_MESSAGE = 'Inserisci un prezzo, ad esempio 249,00';

const specificationSchema = z.object({
  label: z.string().trim().min(1, "Inserisci l'etichetta.").max(64, 'Usa al massimo 64 caratteri.'),
  value: z.string().trim().min(1, 'Inserisci il valore.').max(255, 'Usa al massimo 255 caratteri.'),
});

const imageSchema = z.object({
  id: z.number(),
  url: z.string(),
  alt: z
    .string()
    .trim()
    .min(1, 'Inserisci il testo alternativo.')
    .max(255, 'Usa al massimo 255 caratteri.'),
});

export const productSchema = z.object({
  name: z
    .string()
    .trim()
    .min(1, 'Inserisci il nome del prodotto.')
    .max(255, 'Usa al massimo 255 caratteri.'),
  sku: z
    .string()
    .trim()
    .min(1, 'Inserisci lo SKU.')
    .max(64, 'Usa al massimo 64 caratteri.')
    .regex(/^[A-Z0-9-]+$/, 'Usa solo lettere maiuscole, numeri e trattini'),
  category_id: z.string().min(1, 'Scegli una categoria'),
  short_description: z
    .string()
    .trim()
    .min(1, 'Inserisci la descrizione breve.')
    .max(SHORT_DESCRIPTION_MAX_LENGTH, 'Usa al massimo 160 caratteri.'),
  description: z.string().trim().max(5000, 'Usa al massimo 5000 caratteri.'),
  specifications: z
    .array(specificationSchema)
    .max(MAX_SPECIFICATIONS, 'Puoi aggiungere al massimo 20 righe.'),
  price: z
    .string()
    .trim()
    .regex(EURO_PRICE_PATTERN, PRICE_MESSAGE)
    .refine((value) => parseEuroToCents(value) >= 1, PRICE_MESSAGE),
  stock: z
    .string()
    .trim()
    .regex(/^\d{1,6}$/, 'Inserisci un numero intero di pezzi, da 0 a 999999.'),
  is_active: z.boolean(),
  images: z.array(imageSchema),
});

export const emptyProduct = {
  name: '',
  sku: '',
  category_id: '',
  short_description: '',
  description: '',
  specifications: [],
  price: '',
  stock: '0',
  is_active: false,
  images: [],
};

export function toFormImage(image) {
  return { id: image.id, url: image.url, alt: image.alt };
}

export function productFormValues(product) {
  if (!product) {
    return emptyProduct;
  }

  return {
    name: product.name,
    sku: product.sku,
    category_id: product.category ? String(product.category.id) : '',
    short_description: product.short_description,
    description: product.description ?? '',
    specifications: product.specifications.map(({ label, value }) => ({ label, value })),
    price: formatCentsForInput(product.price),
    stock: String(product.stock),
    is_active: product.is_active,
    images: (product.images ?? []).map(toFormImage),
  };
}

export function productPayload(values, { withImages }) {
  const payload = {
    name: values.name.trim(),
    sku: values.sku.trim(),
    category_id: Number(values.category_id),
    short_description: values.short_description.trim(),
    description: values.description.trim() === '' ? null : values.description.trim(),
    specifications: values.specifications.map(({ label, value }) => ({
      label: label.trim(),
      value: value.trim(),
    })),
    price: parseEuroToCents(values.price),
    stock: Number(values.stock),
    is_active: values.is_active,
  };

  if (withImages) {
    payload.images = values.images.map(({ id, alt }) => ({ id, alt: alt.trim() }));
  }

  return payload;
}

const FIELD_PATTERN =
  /^(name|sku|category_id|short_description|description|price|stock|is_active|specifications\.\d+\.(label|value)|images\.\d+\.alt)$/;

export function productFieldNames(fieldErrors) {
  return Object.keys(fieldErrors ?? {}).filter((field) => FIELD_PATTERN.test(field));
}

export function countFieldErrors(errors) {
  return Object.entries(errors ?? {}).reduce((count, [key, error]) => {
    if (key === 'root' || error === undefined || error === null) {
      return count;
    }

    if (typeof error.message === 'string' && error.type !== undefined) {
      return count + 1;
    }

    return count + countFieldErrors(error);
  }, 0);
}
