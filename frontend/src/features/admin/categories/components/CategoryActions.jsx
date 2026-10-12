import DeleteOutlined from '@mui/icons-material/DeleteOutlined';
import EditOutlined from '@mui/icons-material/EditOutlined';
import IconButton from '@mui/material/IconButton';
import Stack from '@mui/material/Stack';
import Tooltip from '@mui/material/Tooltip';

function deleteBlockedReason(category) {
  if (category.can_delete) {
    return null;
  }

  return category.products_count > 0
    ? 'Contiene prodotti: spostali prima di eliminarla'
    : 'È collegata a prodotti eliminati: non si può eliminare';
}

export function CategoryActions({ category, onEdit, onDelete }) {
  const blockedReason = deleteBlockedReason(category);
  const deleteButton = (
    <IconButton
      aria-label={`Elimina ${category.name}`}
      disabled={blockedReason !== null}
      onClick={() => onDelete(category)}
    >
      <DeleteOutlined />
    </IconButton>
  );

  return (
    <Stack direction="row" sx={{ justifyContent: 'flex-end' }}>
      <IconButton aria-label={`Modifica ${category.name}`} onClick={() => onEdit(category)}>
        <EditOutlined />
      </IconButton>
      {blockedReason ? (
        <Tooltip title={blockedReason}>
          <span>{deleteButton}</span>
        </Tooltip>
      ) : (
        deleteButton
      )}
    </Stack>
  );
}
