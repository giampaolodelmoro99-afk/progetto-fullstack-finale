import MenuItem from '@mui/material/MenuItem';
import TextField from '@mui/material/TextField';

export function FilterSelect({ label, value, allLabel, options, onChange, sx }) {
  return (
    <TextField
      select
      label={label}
      value={value}
      onChange={(event) => onChange(event.target.value)}
      sx={sx}
      slotProps={{ select: { displayEmpty: true }, inputLabel: { shrink: true } }}
    >
      <MenuItem value="">{allLabel}</MenuItem>
      {options.map((option) => (
        <MenuItem key={option.value} value={option.value}>
          {option.label}
        </MenuItem>
      ))}
    </TextField>
  );
}
