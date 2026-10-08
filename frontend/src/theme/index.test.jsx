import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableFooter from '@mui/material/TableFooter';
import TablePagination from '@mui/material/TablePagination';
import TableRow from '@mui/material/TableRow';
import { ThemeProvider } from '@mui/material/styles';
import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { theme } from '@/theme';
import { availabilityColors, orderStatusColors } from '@/theme/statusColors';

describe('theme', () => {
  it('uses the colors of the design system', () => {
    expect(theme.palette.primary.main).toBe('#1C1B19');
    expect(theme.palette.secondary.main).toBe('#B89560');
    expect(theme.palette.background.default).toBe('#FAF7F0');
    expect(theme.palette.background.paper).toBe('#FFFFFF');
    expect(theme.palette.background.inverse).toBe('#1C1B19');
    expect(theme.palette.text.secondary).toBe('#5E5A53');
    expect(theme.palette.divider).toBe('#E4DDCF');
    expect(theme.palette.error.main).toBe('#A2312B');
  });

  it('uses the self hosted fonts', () => {
    expect(theme.typography.fontFamily).toMatch(/^"Manrope Variable"/);
    expect(theme.typography.h1.fontFamily).toMatch(/^"Bodoni Moda Variable"/);
    expect(theme.typography.price.fontFamily).toMatch(/^"Bodoni Moda Variable"/);
    expect(theme.typography.body1.fontFamily).toMatch(/^"Manrope Variable"/);
    expect(theme.typography.captionStrong.fontWeight).toBe(600);
  });

  it('defines the layout tokens', () => {
    expect(theme.layout.adminDrawerWidth).toBe(264);
    expect(theme.layout.assistantPanelWidth).toBe(440);
    expect(theme.layout.minPageHeight).toBe('100vh');
    expect(theme.layout.drawerMaxWidth).toBe('85vw');
    expect(theme.layout.emptyStateTextMaxWidth).toBe('48ch');
    expect(theme.layout.footerTaglineMaxWidth).toBe('32ch');
    expect(theme.layout.navigationUnderlineOffset).toBe(6);
    expect(theme.layout.heroTextMaxWidth).toBe('48ch');
    expect(theme.layout.anchorScrollOffset).toBe(160);
    expect(theme.layout.mobileActionBarHeight).toBe(80);
    expect(theme.layout.stickySummaryTop).toBe(120);
    expect(theme.shape.borderRadius).toBe(4);
  });

  it('lifts only clickable cards on hover', () => {
    const styles = theme.components.MuiCard.styleOverrides.root({ theme });

    expect(styles['&:has(.MuiCardActionArea-root):hover']).toEqual({
      boxShadow: theme.shadows[1],
    });
    expect(styles['&:hover']).toBeUndefined();
  });

  it('shows the table pagination in italian', () => {
    render(
      <ThemeProvider theme={theme}>
        <Table>
          <TableBody />
          <TableFooter>
            <TableRow>
              <TablePagination
                count={42}
                page={0}
                rowsPerPage={10}
                rowsPerPageOptions={[10, 25, 50]}
                onPageChange={() => {}}
              />
            </TableRow>
          </TableFooter>
        </Table>
      </ThemeProvider>,
    );

    expect(screen.getByText('Righe per pagina')).toBeInTheDocument();
    expect(screen.getByText('1–10 di 42')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Vai alla pagina successiva' })).toBeEnabled();
  });
});

describe('status colors', () => {
  it('maps every order status to a chip color', () => {
    expect(orderStatusColors).toEqual({
      pending: 'warning',
      paid: 'info',
      shipped: 'secondary',
      delivered: 'success',
      cancelled: 'error',
    });
  });

  it('maps every availability to a text color', () => {
    expect(availabilityColors).toEqual({
      available: 'success.main',
      low_stock: 'warning.main',
      out_of_stock: 'text.secondary',
    });
  });
});
