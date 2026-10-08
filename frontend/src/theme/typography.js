export const serifFontFamily =
  '"Bodoni Moda Variable", "Bodoni Moda", "Didot", "Bodoni 72", Georgia, serif';

export const sansFontFamily = '"Manrope Variable", "Manrope", "Helvetica Neue", Arial, sans-serif';

export const typography = {
  fontFamily: sansFontFamily,
  htmlFontSize: 16,
  h1: {
    fontFamily: serifFontFamily,
    fontSize: '3.5rem',
    lineHeight: '4rem',
    fontWeight: 400,
    letterSpacing: '-0.01em',
  },
  h2: {
    fontFamily: serifFontFamily,
    fontSize: '2.75rem',
    lineHeight: '3.25rem',
    fontWeight: 400,
    letterSpacing: '-0.005em',
  },
  h3: {
    fontFamily: serifFontFamily,
    fontSize: '2.125rem',
    lineHeight: '2.625rem',
    fontWeight: 400,
  },
  h4: { fontFamily: serifFontFamily, fontSize: '1.75rem', lineHeight: '2.25rem', fontWeight: 500 },
  h5: {
    fontFamily: serifFontFamily,
    fontSize: '1.375rem',
    lineHeight: '1.875rem',
    fontWeight: 500,
  },
  h6: {
    fontFamily: serifFontFamily,
    fontSize: '1.125rem',
    lineHeight: '1.625rem',
    fontWeight: 600,
  },
  subtitle1: { fontSize: '1rem', lineHeight: '1.5rem', fontWeight: 500 },
  subtitle2: { fontSize: '0.875rem', lineHeight: '1.375rem', fontWeight: 600 },
  body1: { fontSize: '1rem', lineHeight: '1.625rem', fontWeight: 400 },
  body2: { fontSize: '0.875rem', lineHeight: '1.375rem', fontWeight: 400 },
  button: {
    fontSize: '0.875rem',
    lineHeight: '1.25rem',
    fontWeight: 600,
    letterSpacing: '0.04em',
    textTransform: 'none',
  },
  caption: {
    fontSize: '0.75rem',
    lineHeight: '1.125rem',
    fontWeight: 400,
    letterSpacing: '0.01em',
  },
  overline: {
    fontSize: '0.6875rem',
    lineHeight: '1rem',
    fontWeight: 600,
    letterSpacing: '0.16em',
    textTransform: 'uppercase',
  },
  price: {
    fontFamily: serifFontFamily,
    fontSize: '1.375rem',
    lineHeight: '1.75rem',
    fontWeight: 500,
    fontVariantNumeric: 'lining-nums',
  },
  priceLarge: {
    fontFamily: serifFontFamily,
    fontSize: '2rem',
    lineHeight: '2.5rem',
    fontWeight: 500,
    fontVariantNumeric: 'lining-nums',
  },
  logo: {
    fontFamily: serifFontFamily,
    fontSize: '1.5rem',
    lineHeight: '2rem',
    fontWeight: 400,
    letterSpacing: '-0.01em',
  },
};
