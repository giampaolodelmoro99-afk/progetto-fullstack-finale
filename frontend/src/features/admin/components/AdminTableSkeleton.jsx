import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableRow from '@mui/material/TableRow';

const ROWS = 10;

function CellSkeleton({ shape }) {
  if (shape === 'thumbnail') {
    return (
      <Skeleton
        variant="rounded"
        sx={{ width: (theme) => theme.spacing(7), height: (theme) => theme.spacing(7) }}
      />
    );
  }

  if (shape === 'actions') {
    return (
      <Stack direction="row" sx={{ gap: 1, justifyContent: 'flex-end' }}>
        {[0, 1].map((index) => (
          <Skeleton
            key={index}
            variant="circular"
            sx={{ width: (theme) => theme.spacing(4), height: (theme) => theme.spacing(4) }}
          />
        ))}
      </Stack>
    );
  }

  if (shape === 'chip') {
    return <Skeleton variant="rounded" sx={{ width: (theme) => theme.spacing(9) }} />;
  }

  return <Skeleton variant="text" />;
}

export function AdminTableSkeleton({ columns }) {
  return (
    <TableBody aria-busy="true">
      {Array.from({ length: ROWS }, (_, row) => (
        <TableRow key={row}>
          {columns.map((column) => (
            <TableCell key={column.key}>
              <CellSkeleton shape={column.skeleton} />
            </TableCell>
          ))}
        </TableRow>
      ))}
    </TableBody>
  );
}
