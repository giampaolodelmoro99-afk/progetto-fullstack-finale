import MenuItem from '@mui/material/MenuItem';
import TextField from '@mui/material/TextField';
import { sortOptions } from '@/features/catalog/catalogFilters';

export function SortSelect({ value, onChange }) {
  return (
    <TextField
      select
      size="small"
      label="Ordina per"
      value={value}
      onChange={(event) => onChange(event.target.value)}
      sx={(theme) => ({ width: { xs: 1, sm: theme.spacing(28) } })}
    >
      {sortOptions.map((option) => (
        <MenuItem key={option.value} value={option.value}>
          {option.label}
        </MenuItem>
      ))}
    </TextField>
  );
}
