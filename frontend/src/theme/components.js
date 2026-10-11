import { getContrastRatio } from '@mui/material/styles';

const MIN_TEXT_CONTRAST = 4.5;

const SOFT_CHIP_COLORS = ['secondary', 'info', 'success', 'warning', 'error'];

export function softChipTextColor(palette, color) {
  const { main, light, dark } = palette[color];

  return getContrastRatio(main, light) >= MIN_TEXT_CONTRAST ? main : dark;
}

function softChipVariant(color) {
  return {
    props: { variant: 'soft', color },
    style: ({ theme }) => {
      const text = softChipTextColor(theme.palette, color);

      return {
        backgroundColor: theme.palette[color].light,
        color: text,
        '& .MuiChip-icon': { color: text },
      };
    },
  };
}

function focusRing(theme) {
  return `0 0 0 2px ${theme.palette.background.paper}, 0 0 0 4px ${theme.palette.focusRing}`;
}

export const components = {
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
        captionStrong: 'span',
        overline: 'span',
        inherit: 'p',
        price: 'p',
        priceLarge: 'p',
        code: 'span',
        logo: 'span',
      },
    },
  },
  MuiButtonBase: {
    defaultProps: { disableRipple: true },
    styleOverrides: {
      root: ({ theme }) => ({ '&.Mui-focusVisible': { boxShadow: focusRing(theme) } }),
    },
  },
  MuiButton: {
    defaultProps: { disableElevation: true },
    styleOverrides: {
      root: ({ theme }) => ({
        height: 44,
        paddingInline: theme.spacing(3),
        borderRadius: theme.shape.borderRadius,
        '&.Mui-focusVisible': { boxShadow: focusRing(theme) },
      }),
      sizeSmall: ({ theme }) => ({ height: 36, paddingInline: theme.spacing(2) }),
      sizeLarge: ({ theme }) => ({ height: 52, paddingInline: theme.spacing(4) }),
      containedPrimary: ({ theme }) => ({
        '&:hover': { backgroundColor: theme.palette.primary.light },
        '&:active': { backgroundColor: theme.palette.primary.dark },
      }),
      containedSecondary: ({ theme }) => ({
        '&:hover': { backgroundColor: theme.palette.secondary.hover },
      }),
      outlined: ({ theme }) => ({
        borderColor: theme.palette.text.primary,
        color: theme.palette.text.primary,
        '&:hover': {
          borderColor: theme.palette.text.primary,
          backgroundColor: theme.palette.action.hover,
        },
        '&.Mui-disabled': { borderColor: theme.palette.action.disabledBackground },
      }),
      text: ({ theme }) => ({
        color: theme.palette.text.primary,
        paddingInline: theme.spacing(2),
        '&:hover': {
          backgroundColor: theme.palette.action.hover,
          textDecoration: 'underline',
          textUnderlineOffset: 4,
        },
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
        '& .MuiOutlinedInput-notchedOutline': {
          borderColor: theme.palette.borderInput,
          '& legend': { fontSize: '0.75rem' },
        },
        '&:hover .MuiOutlinedInput-notchedOutline': { borderColor: theme.palette.text.primary },
        '&.Mui-focused .MuiOutlinedInput-notchedOutline': {
          borderColor: theme.palette.primary.main,
          borderWidth: 2,
        },
        '&.Mui-error .MuiOutlinedInput-notchedOutline': { borderColor: theme.palette.error.main },
        '&.Mui-disabled': { backgroundColor: theme.palette.background.default },
        '&.Mui-disabled .MuiOutlinedInput-notchedOutline': {
          borderColor: theme.palette.action.disabledBackground,
        },
      }),
      input: ({ theme }) => ({
        ...theme.typography.body1,
        padding: theme.spacing(1.625, 2),
        '&::placeholder': { color: theme.palette.text.secondary, opacity: 1 },
      }),
      sizeSmall: {
        minHeight: 44,
      },
      inputSizeSmall: ({ theme }) => ({
        padding: theme.spacing(1.25, 1.5),
      }),
    },
  },
  MuiFormHelperText: {
    styleOverrides: {
      root: ({ theme }) => ({
        ...theme.typography.caption,
        marginTop: theme.spacing(0.75),
        color: theme.palette.text.secondary,
      }),
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
  MuiListItemButton: {
    styleOverrides: {
      root: ({ theme }) => ({
        borderRadius: theme.shape.borderRadius,
        '&.Mui-selected': { backgroundColor: theme.palette.action.selected },
        '&.Mui-selected:hover': { backgroundColor: theme.palette.action.selected },
      }),
    },
  },
  MuiListItemIcon: {
    styleOverrides: {
      root: ({ theme }) => ({ minWidth: theme.spacing(5), color: theme.palette.text.primary }),
    },
  },
  MuiCheckbox: {
    defaultProps: { color: 'primary' },
    styleOverrides: {
      root: ({ theme }) => ({
        color: theme.palette.borderInput,
        '&.Mui-checked': { color: theme.palette.primary.main },
        '&.Mui-focusVisible svg': { boxShadow: focusRing(theme), borderRadius: 2 },
      }),
    },
  },
  MuiSwitch: {
    styleOverrides: {
      root: { width: 40, height: 24, padding: 0, margin: 8 },
      switchBase: ({ theme }) => ({
        padding: 3,
        '&.Mui-checked': { transform: 'translateX(16px)', color: theme.palette.background.paper },
        '&.Mui-checked + .MuiSwitch-track': {
          backgroundColor: theme.palette.primary.main,
          opacity: 1,
        },
        '&.Mui-disabled + .MuiSwitch-track': {
          backgroundColor: theme.palette.action.disabledBackground,
          opacity: 1,
        },
        '&.Mui-focusVisible + .MuiSwitch-track': { boxShadow: focusRing(theme) },
      }),
      thumb: ({ theme }) => ({
        width: 18,
        height: 18,
        boxShadow: theme.shadows[1],
        color: theme.palette.background.paper,
      }),
      track: ({ theme }) => ({
        borderRadius: 12,
        backgroundColor: theme.palette.borderInput,
        opacity: 1,
      }),
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
      label: ({ theme }) => ({ paddingInline: theme.spacing(1.5) }),
    },
    variants: SOFT_CHIP_COLORS.map(softChipVariant),
  },
  MuiBadge: {
    styleOverrides: {
      badge: ({ theme }) => ({
        minWidth: 20,
        height: 20,
        padding: theme.spacing(0, 0.75),
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
        padding: theme.spacing(1, 2),
        color: theme.palette.text.primary,
        backgroundColor: theme.palette[ownerState.severity || 'success'].light,
        '& .MuiAlert-icon': {
          color: theme.palette[ownerState.severity || 'success'].main,
          opacity: 1,
        },
      }),
    },
  },
  MuiAlertTitle: {
    styleOverrides: {
      root: ({ theme }) => ({ ...theme.typography.subtitle2, marginBottom: theme.spacing(0.25) }),
    },
  },
  MuiCard: {
    defaultProps: { elevation: 0, variant: 'outlined' },
    styleOverrides: {
      root: ({ theme }) => ({
        borderRadius: theme.shape.borderRadius * 2,
        backgroundColor: theme.palette.background.paper,
        transition: 'box-shadow 200ms ease',
        '&:has(.MuiCardActionArea-root):hover': { boxShadow: theme.shadows[1] },
      }),
    },
  },
  MuiCardMedia: {
    styleOverrides: {
      root: ({ theme }) => ({
        aspectRatio: '4 / 5',
        backgroundColor: theme.palette.background.media,
        objectFit: 'contain',
      }),
    },
  },
  MuiCardContent: {
    styleOverrides: {
      root: ({ theme }) => ({
        padding: theme.spacing(2, 3, 3),
        '&:last-child': { paddingBottom: theme.spacing(3) },
      }),
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
      root: ({ theme }) => ({
        minHeight: 72,
        gap: theme.spacing(2),
        paddingInline: theme.spacing(2),
        [theme.breakpoints.up('md')]: {
          minHeight: 72,
          gap: theme.spacing(4),
          paddingInline: theme.spacing(4),
        },
      }),
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
};

export const localizedComponents = {
  MuiTablePagination: {
    defaultProps: {
      labelRowsPerPage: 'Righe per pagina',
      labelDisplayedRows: ({ from, to, count }) =>
        `${from}–${to} di ${count === -1 ? `più di ${to}` : count}`,
    },
  },
};
