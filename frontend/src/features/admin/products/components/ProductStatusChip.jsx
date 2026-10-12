import Chip from '@mui/material/Chip';

export function ProductStatusChip({ isActive }) {
  return isActive ? (
    <Chip size="small" variant="soft" color="success" label="Attivo" />
  ) : (
    <Chip size="small" label="Disattivato" />
  );
}
