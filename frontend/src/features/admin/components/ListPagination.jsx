import ChevronLeft from '@mui/icons-material/ChevronLeft';
import ChevronRight from '@mui/icons-material/ChevronRight';
import Button from '@mui/material/Button';
import Stack from '@mui/material/Stack';
import TablePagination from '@mui/material/TablePagination';
import Typography from '@mui/material/Typography';
import { PER_PAGE_OPTIONS } from '@/features/admin/listParams';
import { useIsDesktop } from '@/hooks/useIsDesktop';

export function ListPagination({ meta, page, perPage, onPageChange, onPerPageChange }) {
  const isDesktop = useIsDesktop();
  const lastPage = meta.last_page;

  if (isDesktop) {
    return (
      <TablePagination
        component="div"
        count={meta.total}
        page={Math.max(0, Math.min(page, lastPage) - 1)}
        rowsPerPage={perPage}
        rowsPerPageOptions={PER_PAGE_OPTIONS}
        onPageChange={(event, newPage) => onPageChange(newPage + 1)}
        onRowsPerPageChange={(event) => onPerPageChange(Number(event.target.value))}
      />
    );
  }

  return (
    <Stack
      component="nav"
      aria-label="Paginazione"
      direction="row"
      sx={{ alignItems: 'center', justifyContent: 'space-between', gap: 1 }}
    >
      <Button
        startIcon={<ChevronLeft />}
        disabled={page <= 1}
        onClick={() => onPageChange(page - 1)}
      >
        Precedenti
      </Button>
      <Typography variant="body2">
        {meta.from ?? 0}–{meta.to ?? 0} di {meta.total}
      </Typography>
      <Button
        endIcon={<ChevronRight />}
        disabled={page >= lastPage}
        onClick={() => onPageChange(page + 1)}
      >
        Successivi
      </Button>
    </Stack>
  );
}
