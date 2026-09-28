import { createTheme } from '@mui/material/styles';

const serif = '"Bodoni Moda Variable", "Bodoni Moda", "Didot", "Bodoni 72", Georgia, serif';
const sans = '"Manrope Variable", "Manrope", "Helvetica Neue", Arial, sans-serif';

const colors = {
  ink: '#1C1B19',
  inkLight: '#3A3834',
  inkMuted: '#5E5A53',
  inkDisabled: '#A29D94',
  ivory: '#FAF7F0',
  paper: '#FFFFFF',
  media: '#F0EBE1',
  divider: '#E4DDCF',
  borderInput: '#8C867B',
  gold: '#B89560',
  goldHover: '#A8854F',
  goldDark: '#76592B',
  goldLight: '#F4ECDD',
  goldOnDark: '#CDB27E',
};

const focusRing = `0 0 0 2px ${colors.paper}, 0 0 0 4px ${colors.goldDark}`;

const shadows = [
  'none',
  '0 1px 2px #1C1B190F, 0 2px 8px #1C1B190A',
  ...Array(7).fill('0 2px 4px #1C1B190F, 0 8px 24px #1C1B1914'),
  ...Array(16).fill('0 4px 8px #1C1B1914, 0 24px 48px #1C1B191F'),
];

const theme = createTheme({
  spacing: 8,
  shape: { borderRadius: 4 },
  shadows,
  palette: {
    mode: 'light',
    primary: { main: colors.ink, light: colors.inkLight, dark: '#000000', contrastText: colors.ivory },
    secondary: { main: colors.gold, light: colors.goldLight, dark: colors.goldDark, contrastText: colors.ink },
    background: { default: colors.ivory, paper: colors.paper, media: colors.media, inverse: colors.ink },
    text: { primary: colors.ink, secondary: colors.inkMuted, disabled: colors.inkDisabled, inverse: colors.ivory },
    divider: colors.divider,
    success: { main: '#2E6B4E', light: '#E4EFE8', dark: '#1F4D37', contrastText: '#FFFFFF' },
    warning: { main: '#9A4A12', light: '#F7E9DC', dark: '#6F350D', contrastText: '#FFFFFF' },
    error: { main: '#A2312B', light: '#F7E3E0', dark: '#76231F', contrastText: '#FFFFFF' },
    info: { main: '#2B5E78', light: '#E1EDF2', dark: '#1E4356', contrastText: '#FFFFFF' },
    action: {
      hover: '#1C1B190A',
      selected: '#1C1B1914',
      disabled: '#1C1B1961',
      disabledBackground: '#1C1B191F',
    },
    borderInput: colors.borderInput,
    inverseAccent: colors.goldOnDark,
  },
  typography: {
    fontFamily: sans,
    htmlFontSize: 16,
    h1: { fontFamily: serif, fontSize: '3.5rem', lineHeight: '4rem', fontWeight: 400, letterSpacing: '-0.01em' },
    h2: { fontFamily: serif, fontSize: '2.75rem', lineHeight: '3.25rem', fontWeight: 400, letterSpacing: '-0.005em' },
    h3: { fontFamily: serif, fontSize: '2.125rem', lineHeight: '2.625rem', fontWeight: 400 },
    h4: { fontFamily: serif, fontSize: '1.75rem', lineHeight: '2.25rem', fontWeight: 500 },
    h5: { fontFamily: serif, fontSize: '1.375rem', lineHeight: '1.875rem', fontWeight: 500 },
    h6: { fontFamily: serif, fontSize: '1.125rem', lineHeight: '1.625rem', fontWeight: 600 },
    subtitle1: { fontSize: '1rem', lineHeight: '1.5rem', fontWeight: 500 },
    subtitle2: { fontSize: '0.875rem', lineHeight: '1.375rem', fontWeight: 600 },
    body1: { fontSize: '1rem', lineHeight: '1.625rem', fontWeight: 400 },
    body2: { fontSize: '0.875rem', lineHeight: '1.375rem', fontWeight: 400 },
    button: { fontSize: '0.875rem', lineHeight: '1.25rem', fontWeight: 600, letterSpacing: '0.04em', textTransform: 'none' },
    caption: { fontSize: '0.75rem', lineHeight: '1.125rem', fontWeight: 400, letterSpacing: '0.01em' },
    overline: { fontSize: '0.6875rem', lineHeight: '1rem', fontWeight: 600, letterSpacing: '0.16em', textTransform: 'uppercase' },
    price: { fontFamily: serif, fontSize: '1.375rem', lineHeight: '1.75rem', fontWeight: 500, fontVariantNumeric: 'lining-nums' },
    priceLarge: { fontFamily: serif, fontSize: '2rem', lineHeight: '2.5rem', fontWeight: 500, fontVariantNumeric: 'lining-nums' },
  },
  components: {
    MuiCssBaseline: {
      styleOverrides: {
        body: { fontOpticalSizing: 'auto', WebkitFontSmoothing: 'antialiased' },
      },
    },
    MuiTypography: {
      defaultProps: {
        variantMapping: {
          h1: 'h1',
          h2: 'h2',
          h3: 'h3',
          h4: 'h4',
          h5: 'h5',
          h6: 'h6',
          subtitle1: 'p',
          subtitle2: 'p',
          body1: 'p',
          body2: 'p',
          caption: 'span',
          overline: 'span',
          inherit: 'p',
          price: 'p',
          priceLarge: 'p',
        },
      },
    },
    MuiButtonBase: {
      defaultProps: { disableRipple: true },
      styleOverrides: {
        root: { '&.Mui-focusVisible': { boxShadow: focusRing } },
      },
    },
    MuiButton: {
      defaultProps: { disableElevation: true },
      styleOverrides: {
        root: ({ theme }) => ({
          height: 44,
          paddingInline: theme.spacing(3),
          borderRadius: theme.shape.borderRadius,
          '&.Mui-focusVisible': { boxShadow: focusRing },
        }),
        sizeSmall: ({ theme }) => ({ height: 36, paddingInline: theme.spacing(2) }),
        sizeLarge: ({ theme }) => ({ height: 52, paddingInline: theme.spacing(4) }),
        containedPrimary: ({ theme }) => ({
          '&:hover': { backgroundColor: theme.palette.primary.light },
          '&:active': { backgroundColor: theme.palette.primary.dark },
        }),
        containedSecondary: {
          '&:hover': { backgroundColor: colors.goldHover },
        },
        outlined: ({ theme }) => ({
          borderColor: theme.palette.text.primary,
          color: theme.palette.text.primary,
          '&:hover': { borderColor: theme.palette.text.primary, backgroundColor: theme.palette.action.hover },
          '&.Mui-disabled': { borderColor: theme.palette.action.disabledBackground },
        }),
        text: ({ theme }) => ({
          color: theme.palette.text.primary,
          paddingInline: theme.spacing(2),
          '&:hover': { backgroundColor: theme.palette.action.hover, textDecoration: 'underline', textUnderlineOffset: 4 },
        }),
      },
    },
    MuiIconButton: {
      styleOverrides: {
        root: ({ theme }) => ({ color: theme.palette.text.primary }),
      },
    },
    MuiTextField: {
      defaultProps: {
        variant: 'outlined',
        fullWidth: true,
        slotProps: { inputLabel: { shrink: true } },
      },
    },
    MuiInputLabel: {
      styleOverrides: {
        root: ({ theme }) => ({
          ...theme.typography.caption,
          fontWeight: 500,
          color: theme.palette.text.secondary,
          '&.MuiInputLabel-shrink': { transform: 'translate(14px, -8px) scale(1)' },
          '&.Mui-focused': { color: theme.palette.text.primary },
          '&.Mui-error': { color: theme.palette.error.main },
        }),
      },
    },
    MuiOutlinedInput: {
      defaultProps: { notched: true },
      styleOverrides: {
        root: ({ theme }) => ({
          backgroundColor: theme.palette.background.paper,
          borderRadius: theme.shape.borderRadius,
          minHeight: 52,
          '& .MuiOutlinedInput-notchedOutline': { borderColor: theme.palette.borderInput, '& legend': { fontSize: '0.75rem' } },
          '&:hover .MuiOutlinedInput-notchedOutline': { borderColor: theme.palette.text.primary },
          '&.Mui-focused .MuiOutlinedInput-notchedOutline': { borderColor: theme.palette.primary.main, borderWidth: 2 },
          '&.Mui-error .MuiOutlinedInput-notchedOutline': { borderColor: theme.palette.error.main },
          '&.Mui-disabled': { backgroundColor: theme.palette.background.default },
          '&.Mui-disabled .MuiOutlinedInput-notchedOutline': { borderColor: theme.palette.action.disabledBackground },
        }),
        input: ({ theme }) => ({
          ...theme.typography.body1,
          padding: '13px 16px',
          '&::placeholder': { color: theme.palette.text.secondary, opacity: 1 },
        }),
      },
    },
    MuiFormHelperText: {
      styleOverrides: {
        root: ({ theme }) => ({ ...theme.typography.caption, marginTop: 6, color: theme.palette.text.secondary }),
      },
    },
    MuiMenu: {
      defaultProps: { slotProps: { paper: { elevation: 2 } } },
    },
    MuiMenuItem: {
      styleOverrides: {
        root: ({ theme }) => ({
          ...theme.typography.body1,
          minHeight: 44,
          '&.Mui-selected': { backgroundColor: theme.palette.action.selected, fontWeight: 500 },
          '&.Mui-selected:hover': { backgroundColor: theme.palette.action.selected },
        }),
      },
    },
    MuiCheckbox: {
      defaultProps: { color: 'primary' },
      styleOverrides: {
        root: ({ theme }) => ({
          color: theme.palette.borderInput,
          '&.Mui-checked': { color: theme.palette.primary.main },
          '&.Mui-focusVisible svg': { boxShadow: focusRing, borderRadius: 2 },
        }),
      },
    },
    MuiSwitch: {
      styleOverrides: {
        root: { width: 40, height: 24, padding: 0, margin: 8 },
        switchBase: ({ theme }) => ({
          padding: 3,
          '&.Mui-checked': { transform: 'translateX(16px)', color: theme.palette.background.paper },
          '&.Mui-checked + .MuiSwitch-track': { backgroundColor: theme.palette.primary.main, opacity: 1 },
          '&.Mui-disabled + .MuiSwitch-track': { backgroundColor: theme.palette.action.disabledBackground, opacity: 1 },
          '&.Mui-focusVisible + .MuiSwitch-track': { boxShadow: focusRing },
        }),
        thumb: { width: 18, height: 18, boxShadow: '0 1px 2px #1C1B1933', color: colors.paper },
        track: ({ theme }) => ({ borderRadius: 12, backgroundColor: theme.palette.borderInput, opacity: 1 }),
      },
    },
    MuiFormControlLabel: {
      styleOverrides: {
        label: ({ theme }) => ({ ...theme.typography.body2 }),
      },
    },
    MuiChip: {
      styleOverrides: {
        root: ({ theme }) => ({
          height: 28,
          borderRadius: theme.shape.borderRadius,
          fontSize: '0.75rem',
          lineHeight: '1rem',
          fontWeight: 600,
          letterSpacing: '0.02em',
        }),
        label: { paddingInline: 12 },
      },
    },
    MuiBadge: {
      styleOverrides: {
        badge: ({ theme }) => ({
          minWidth: 20,
          height: 20,
          padding: '0 6px',
          fontSize: '0.6875rem',
          fontWeight: 700,
          boxShadow: `0 0 0 2px ${theme.palette.background.paper}`,
        }),
      },
    },
    MuiAlert: {
      defaultProps: { variant: 'standard' },
      styleOverrides: {
        root: ({ theme, ownerState }) => ({
          ...theme.typography.body2,
          borderRadius: theme.shape.borderRadius,
          padding: '8px 16px',
          color: theme.palette.text.primary,
          backgroundColor: theme.palette[ownerState.severity || 'success'].light,
          '& .MuiAlert-icon': { color: theme.palette[ownerState.severity || 'success'].main, opacity: 1 },
        }),
      },
    },
    MuiAlertTitle: {
      styleOverrides: {
        root: ({ theme }) => ({ ...theme.typography.subtitle2, marginBottom: 2 }),
      },
    },
    MuiCard: {
      defaultProps: { elevation: 0 },
      styleOverrides: {
        root: ({ theme }) => ({
          borderRadius: 8,
          backgroundColor: theme.palette.background.paper,
          transition: 'box-shadow 200ms ease',
          '&:hover': { boxShadow: theme.shadows[1] },
        }),
      },
    },
    MuiCardMedia: {
      styleOverrides: {
        root: ({ theme }) => ({ aspectRatio: '4 / 5', backgroundColor: theme.palette.background.media, objectFit: 'contain' }),
      },
    },
    MuiCardContent: {
      styleOverrides: {
        root: ({ theme }) => ({ padding: theme.spacing(2, 3, 3), '&:last-child': { paddingBottom: theme.spacing(3) } }),
      },
    },
    MuiAppBar: {
      defaultProps: { color: 'inherit', elevation: 0, position: 'sticky' },
      styleOverrides: {
        root: ({ theme }) => ({
          backgroundColor: theme.palette.background.paper,
          borderBottom: `1px solid ${theme.palette.divider}`,
        }),
      },
    },
    MuiToolbar: {
      styleOverrides: {
        root: ({ theme }) => ({ minHeight: 72, gap: theme.spacing(4), paddingInline: theme.spacing(4) }),
      },
    },
    MuiPaper: {
      styleOverrides: {
        rounded: ({ theme }) => ({ borderRadius: theme.shape.borderRadius }),
      },
    },
    MuiDivider: {
      styleOverrides: {
        root: ({ theme }) => ({ borderColor: theme.palette.divider }),
      },
    },
  },
});

export const ORDER_STATUS = {
  pending: { label: 'In attesa', color: 'warning' },
  paid: { label: 'Pagato', color: 'info' },
  shipped: { label: 'Spedito', color: 'secondary' },
  delivered: { label: 'Consegnato', color: 'success' },
  cancelled: { label: 'Annullato', color: 'error' },
};

export const formatPrice = (value) =>
  new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR', useGrouping: 'always' }).format(value);

export default theme;