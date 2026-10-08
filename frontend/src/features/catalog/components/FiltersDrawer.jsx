import Close from '@mui/icons-material/Close';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Drawer from '@mui/material/Drawer';
import IconButton from '@mui/material/IconButton';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { CatalogFilters } from '@/features/catalog/components/CatalogFilters';

function showLabel(total) {
  if (total === null) {
    return 'Mostra i prodotti';
  }

  return `Mostra ${total} ${total === 1 ? 'prodotto' : 'prodotti'}`;
}

export function FiltersDrawer({ open, onClose, filters, priceBounds, total, onChange, onClear }) {
  return (
    <Drawer
      anchor="left"
      open={open}
      onClose={onClose}
      slotProps={{
        paper: {
          'aria-labelledby': 'catalog-filters-title',
          sx: {
            width: (theme) => theme.layout.storeDrawerWidth,
            maxWidth: (theme) => theme.layout.drawerMaxWidth,
          },
        },
      }}
    >
      <Stack sx={{ height: 1 }}>
        <Stack
          direction="row"
          sx={{
            alignItems: 'center',
            justifyContent: 'space-between',
            px: 2,
            py: 1.5,
            borderBottom: 1,
            borderColor: 'divider',
          }}
        >
          <Typography id="catalog-filters-title" variant="h5" component="h2">
            Filtri
          </Typography>
          <IconButton aria-label="Chiudi i filtri" onClick={onClose}>
            <Close />
          </IconButton>
        </Stack>
        <Box sx={{ flexGrow: 1, overflowY: 'auto', p: 2 }}>
          <CatalogFilters
            filters={filters}
            priceBounds={priceBounds}
            onChange={onChange}
            onClear={onClear}
            showClear={false}
          />
        </Box>
        <Stack direction="row" sx={{ gap: 1.5, p: 2, borderTop: 1, borderColor: 'divider' }}>
          <Button variant="outlined" onClick={onClear} sx={{ flex: 1 }}>
            Azzera
          </Button>
          <Button variant="contained" onClick={onClose} sx={{ flex: 2, whiteSpace: 'nowrap' }}>
            {showLabel(total)}
          </Button>
        </Stack>
      </Stack>
    </Drawer>
  );
}
