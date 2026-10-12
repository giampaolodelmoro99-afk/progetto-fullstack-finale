import TableCell from '@mui/material/TableCell';
import TableHead from '@mui/material/TableHead';
import TableRow from '@mui/material/TableRow';
import Typography from '@mui/material/Typography';

const ADMIN_TABLE_HEADER_SX = { typography: 'body2', color: 'text.secondary' };

export function AdminTableHead({ columns }) {
  return (
    <TableHead>
      <TableRow>
        {columns.map((column) => (
          <TableCell key={column.key} align={column.align} sx={ADMIN_TABLE_HEADER_SX}>
            {column.hidden ? (
              <Typography component="span" sx={(theme) => theme.mixins.visuallyHidden}>
                {column.label}
              </Typography>
            ) : (
              column.label
            )}
          </TableCell>
        ))}
      </TableRow>
    </TableHead>
  );
}
