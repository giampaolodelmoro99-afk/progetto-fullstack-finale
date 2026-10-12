import { describe, expect, it } from 'vitest';
import {
  countFieldErrors,
  emptyProduct,
  productFieldNames,
  productPayload,
  productSchema,
} from '@/features/admin/products/schemas';

const VALID = {
  ...emptyProduct,
  name: 'Cuffie Aria Mini',
  sku: 'CDS-AUD-003',
  category_id: '1',
  short_description: 'Cuffie over-ear compatte, pieghevoli.',
  price: '249,00',
  stock: '12',
};

function errorFor(values, field) {
  const result = productSchema.safeParse(values);

  return result.success
    ? null
    : (result.error.issues.find((issue) => issue.path.join('.') === field)?.message ?? null);
}

describe('productSchema', () => {
  it('accepts a valid product', () => {
    expect(productSchema.safeParse(VALID).success).toBe(true);
  });

  it.each(['cds-aud-001', 'CDS AUD 001', 'CDS_AUD'])('rejects the SKU %s', (sku) => {
    expect(errorFor({ ...VALID, sku }, 'sku')).toBe(
      'Usa solo lettere maiuscole, numeri e trattini',
    );
  });

  it('limits the short description to 160 characters', () => {
    expect(errorFor({ ...VALID, short_description: 'a'.repeat(160) }, 'short_description')).toBe(
      null,
    );
    expect(errorFor({ ...VALID, short_description: 'a'.repeat(161) }, 'short_description')).toBe(
      'Usa al massimo 160 caratteri.',
    );
  });

  it.each(['1.249', '10000,00', '249.00', '', '0,00'])('rejects the price «%s»', (price) => {
    expect(errorFor({ ...VALID, price }, 'price')).toBe('Inserisci un prezzo, ad esempio 249,00');
  });

  it.each(['9999,99', '0,01', '249'])('accepts the price %s', (price) => {
    expect(errorFor({ ...VALID, price }, 'price')).toBeNull();
  });

  it('asks for a category', () => {
    expect(errorFor({ ...VALID, category_id: '' }, 'category_id')).toBe('Scegli una categoria');
  });

  it.each(['-1', '1000000', '2,5'])('rejects the stock %s', (stock) => {
    expect(errorFor({ ...VALID, stock }, 'stock')).toBe(
      'Inserisci un numero intero di pezzi, da 0 a 999999.',
    );
  });
});

describe('productPayload', () => {
  it('sends the price in cents and the numbers as integers', () => {
    expect(
      productPayload(
        {
          ...VALID,
          price: '1249,5',
          description: '  ',
          specifications: [{ label: ' Peso ', value: '250 g' }],
          images: [{ id: 3, url: 'x', alt: ' Vista ' }],
        },
        { withImages: true },
      ),
    ).toEqual({
      name: 'Cuffie Aria Mini',
      sku: 'CDS-AUD-003',
      category_id: 1,
      short_description: 'Cuffie over-ear compatte, pieghevoli.',
      description: null,
      specifications: [{ label: 'Peso', value: '250 g' }],
      price: 124950,
      stock: 12,
      is_active: false,
      images: [{ id: 3, alt: 'Vista' }],
    });
  });

  it('leaves the images out of a new product', () => {
    expect(productPayload(VALID, { withImages: false })).not.toHaveProperty('images');
  });
});

describe('productFieldNames', () => {
  it('keeps only the fields of the form', () => {
    expect(
      productFieldNames({
        sku: 'Questo SKU è già in uso',
        'specifications.1.value': 'Il campo valore è obbligatorio.',
        'images.0.alt': 'Il campo testo alternativo è obbligatorio.',
        'images.0.id': 'Immagine non valida.',
        images: 'Le immagini non sono valide.',
      }),
    ).toEqual(['sku', 'specifications.1.value', 'images.0.alt']);
  });
});

describe('countFieldErrors', () => {
  it('counts the nested errors', () => {
    expect(
      countFieldErrors({
        sku: { type: 'server', message: 'Questo SKU è già in uso' },
        specifications: [
          undefined,
          { value: { type: 'too_small', message: 'Inserisci il valore.' } },
        ],
      }),
    ).toBe(2);
  });
});
