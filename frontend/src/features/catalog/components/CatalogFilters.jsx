import Button from '@mui/material/Button';
import Divider from '@mui/material/Divider';
import FormControlLabel from '@mui/material/FormControlLabel';
import Stack from '@mui/material/Stack';
import Switch from '@mui/material/Switch';
import Typography from '@mui/material/Typography';
import { CategoryFilter } from '@/features/catalog/components/CategoryFilter';
import { PriceFilter } from '@/features/catalog/components/PriceFilter';

function FilterGroup({ title, children }) {
  return (
    <Stack component="fieldset" sx={{ border: 0, m: 0, p: 0, gap: 1.5, minWidth: 0 }}>
      <Typography component="legend" variant="subtitle2" sx={{ p: 0, mb: 1.5 }}>
        {title}
      </Typography>
      {children}
    </Stack>
  );
}

export function CatalogFilters({ filters, priceBounds, onChange, onClear, showClear = true }) {
  return (
    <Stack sx={{ gap: 3 }} divider={<Divider flexItem />}>
      <FilterGroup title="Categoria">
        <CategoryFilter
          value={filters.category}
          onChange={(category) => onChange({ category, minPrice: null, maxPrice: null })}
        />
      </FilterGroup>
      {priceBounds ? (
        <FilterGroup title="Prezzo">
          <PriceFilter
            key={`${priceBounds.min}-${priceBounds.max}-${filters.minPrice}-${filters.maxPrice}`}
            bounds={priceBounds}
            minPrice={filters.minPrice}
            maxPrice={filters.maxPrice}
            onChange={onChange}
          />
        </FilterGroup>
      ) : null}
      <Stack>
        <FormControlLabel
          labelPlacement="start"
          control={
            <Switch
              checked={filters.inStock}
              onChange={(event) => onChange({ inStock: event.target.checked })}
            />
          }
          label="Solo disponibili"
          sx={{ ml: 0, justifyContent: 'space-between' }}
        />
        <FormControlLabel
          labelPlacement="start"
          control={
            <Switch
              checked={filters.isNew}
              onChange={(event) => onChange({ isNew: event.target.checked })}
            />
          }
          label="Solo novità"
          sx={{ ml: 0, justifyContent: 'space-between' }}
        />
      </Stack>
      {showClear ? (
        <Button variant="text" onClick={onClear} sx={{ alignSelf: 'flex-start', px: 0 }}>
          Azzera filtri
        </Button>
      ) : null}
    </Stack>
  );
}
