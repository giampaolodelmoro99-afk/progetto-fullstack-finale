import DeleteOutlined from '@mui/icons-material/DeleteOutlined';
import EditOutlined from '@mui/icons-material/EditOutlined';
import IconButton from '@mui/material/IconButton';
import Stack from '@mui/material/Stack';
import { Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';

export function ProductActions({ product, onDelete }) {
  return (
    <Stack direction="row" sx={{ justifyContent: 'flex-end' }}>
      <IconButton
        component={RouterLink}
        to={paths.adminProductEdit(product.id)}
        aria-label={`Modifica ${product.name}`}
      >
        <EditOutlined />
      </IconButton>
      <IconButton aria-label={`Elimina ${product.name}`} onClick={() => onDelete(product)}>
        <DeleteOutlined />
      </IconButton>
    </Stack>
  );
}
