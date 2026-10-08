import Slider from '@mui/material/Slider';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useState } from 'react';
import { PRICE_STEP } from '@/features/catalog/catalogFilters';
import { formatPrice } from '@/utils/format';

const thumbLabels = ['Prezzo minimo', 'Prezzo massimo'];

function clamp(value, bounds) {
  return Math.min(Math.max(value, bounds.min), bounds.max);
}

export function PriceFilter({ bounds, minPrice, maxPrice, onChange }) {
  const [value, setValue] = useState(() => [
    clamp(minPrice ?? bounds.min, bounds),
    clamp(maxPrice ?? bounds.max, bounds),
  ]);

  function handleCommit(event, [low, high]) {
    onChange({
      minPrice: low <= bounds.min ? null : low,
      maxPrice: high >= bounds.max ? null : high,
    });
  }

  return (
    <Stack sx={{ gap: 0.5, px: 1 }}>
      <Slider
        value={value}
        min={bounds.min}
        max={bounds.max}
        step={PRICE_STEP}
        disableSwap
        onChange={(event, next) => setValue(next)}
        onChangeCommitted={handleCommit}
        getAriaLabel={(index) => thumbLabels[index]}
        getAriaValueText={(cents) => formatPrice(cents)}
      />
      <Stack direction="row" sx={{ justifyContent: 'space-between', mx: -1 }}>
        <Typography variant="caption">{formatPrice(value[0])}</Typography>
        <Typography variant="caption">{formatPrice(value[1])}</Typography>
      </Stack>
    </Stack>
  );
}
