import Add from '@mui/icons-material/Add';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';
import Table from '@mui/material/Table';
import TableBody from '@mui/material/TableBody';
import TableCell from '@mui/material/TableCell';
import TableContainer from '@mui/material/TableContainer';
import TableRow from '@mui/material/TableRow';
import Typography from '@mui/material/Typography';
import { useState } from 'react';
import { ErrorState } from '@/components/feedback/ErrorState';
import { CategoryActions } from '@/features/admin/categories/components/CategoryActions';
import { CategoryDialog } from '@/features/admin/categories/components/CategoryDialog';
import { DeleteCategoryDialog } from '@/features/admin/categories/components/DeleteCategoryDialog';
import { useAdminCategories } from '@/features/admin/categories/hooks/useAdminCategories';
import { AdminTableHead } from '@/features/admin/components/AdminTableHead';
import { useIsDesktop } from '@/hooks/useIsDesktop';

const COLUMNS = [
  { key: 'name', label: 'Nome' },
  { key: 'slug', label: 'Slug' },
  { key: 'description', label: 'Descrizione' },
  { key: 'products', label: 'Prodotti', align: 'right' },
  { key: 'actions', label: 'Azioni', align: 'right' },
];

function categoryCount(total) {
  return `${total} ${total === 1 ? 'categoria' : 'categorie'}`;
}

function CategoriesTable({ categories, onEdit, onDelete }) {
  return (
    <Card>
      <TableContainer>
        <Table aria-label="Categorie">
          <AdminTableHead columns={COLUMNS} />
          <TableBody>
            {categories.map((category) => (
              <TableRow key={category.id}>
                <TableCell>
                  <Typography variant="subtitle2" component="span">
                    {category.name}
                  </Typography>
                </TableCell>
                <TableCell sx={{ color: 'text.secondary' }}>{category.slug}</TableCell>
                <TableCell>{category.description}</TableCell>
                <TableCell align="right">{category.products_count}</TableCell>
                <TableCell align="right">
                  <CategoryActions category={category} onEdit={onEdit} onDelete={onDelete} />
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </TableContainer>
    </Card>
  );
}

function CategoryCards({ categories, onEdit, onDelete }) {
  return (
    <Stack component="ul" aria-label="Categorie" sx={{ listStyle: 'none', m: 0, p: 0, gap: 1.5 }}>
      {categories.map((category) => (
        <li key={category.id}>
          <Card>
            <CardContent>
              <Stack direction="row" sx={{ gap: 1, alignItems: 'flex-start' }}>
                <Stack sx={{ flexGrow: 1, minWidth: 0, gap: 0.5 }}>
                  <Typography variant="subtitle2" component="h3">
                    {category.name}
                  </Typography>
                  <Typography variant="caption" sx={{ color: 'text.secondary' }}>
                    {category.slug} · Prodotti: {category.products_count}
                  </Typography>
                  {category.description ? (
                    <Typography variant="body2">{category.description}</Typography>
                  ) : null}
                </Stack>
                <CategoryActions category={category} onEdit={onEdit} onDelete={onDelete} />
              </Stack>
            </CardContent>
          </Card>
        </li>
      ))}
    </Stack>
  );
}

export default function CategoriesPage() {
  const categoriesQuery = useAdminCategories();
  const isDesktop = useIsDesktop();
  const [editing, setEditing] = useState(null);
  const [deleting, setDeleting] = useState(null);
  const categories = categoriesQuery.data ?? [];

  return (
    <Stack sx={{ gap: 3 }}>
      <Stack
        direction={{ xs: 'column', md: 'row' }}
        sx={{ gap: 2, justifyContent: 'space-between', alignItems: { md: 'center' } }}
      >
        <Typography variant="body2" sx={{ color: 'text.secondary' }}>
          {categoriesQuery.isSuccess
            ? `${categoryCount(categories.length)} · una categoria con prodotti non si può eliminare`
            : 'Una categoria con prodotti non si può eliminare'}
        </Typography>
        <Button variant="contained" startIcon={<Add />} onClick={() => setEditing('new')}>
          Nuova categoria
        </Button>
      </Stack>
      {categoriesQuery.isPending ? (
        <Stack sx={{ gap: 1 }} role="status" aria-label="Caricamento delle categorie">
          {[0, 1, 2, 3, 4, 5].map((index) => (
            <Skeleton key={index} variant="rounded" sx={{ height: (theme) => theme.spacing(7) }} />
          ))}
        </Stack>
      ) : null}
      {categoriesQuery.isError ? (
        <ErrorState
          title="Non è stato possibile caricare le categorie"
          message="Controlla la connessione e riprova."
          onRetry={() => categoriesQuery.refetch()}
        />
      ) : null}
      {categoriesQuery.isSuccess && categories.length === 0 ? (
        <Typography variant="body2" sx={{ color: 'text.secondary' }}>
          Non ci sono ancora categorie.
        </Typography>
      ) : null}
      {categories.length > 0 && isDesktop ? (
        <CategoriesTable categories={categories} onEdit={setEditing} onDelete={setDeleting} />
      ) : null}
      {categories.length > 0 && !isDesktop ? (
        <CategoryCards categories={categories} onEdit={setEditing} onDelete={setDeleting} />
      ) : null}
      {editing ? (
        <CategoryDialog
          category={editing === 'new' ? null : editing}
          onClose={() => setEditing(null)}
        />
      ) : null}
      {deleting ? (
        <DeleteCategoryDialog category={deleting} onClose={() => setDeleting(null)} />
      ) : null}
    </Stack>
  );
}
