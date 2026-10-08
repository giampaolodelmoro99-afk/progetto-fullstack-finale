import MenuItem from '@mui/material/MenuItem';
import TextField from '@mui/material/TextField';
import { VisuallyHidden } from '@/components/feedback/VisuallyHidden';

export function QuantitySelect({ productName, value, max, disabled, onChange }) {
  const options = Array.from({ length: Math.max(max, 1) }, (_, index) => index + 1);
  const outOfRange = value > max;

  return (
    <TextField
      select
      size="small"
      label={
        <>
          <span aria-hidden="true">Quantità</span>
          <VisuallyHidden>Quantità di {productName}</VisuallyHidden>
        </>
      }
      value={value}
      disabled={disabled}
      onChange={(event) => onChange(Number(event.target.value))}
      sx={{ width: (theme) => theme.spacing(12), flexShrink: 0 }}
    >
      {options.map((option) => (
        <MenuItem key={option} value={option}>
          {option}
        </MenuItem>
      ))}
      {outOfRange ? (
        <MenuItem value={value} disabled>
          {value}
        </MenuItem>
      ) : null}
    </TextField>
  );
}
