import Check from '@mui/icons-material/Check';
import Chip from '@mui/material/Chip';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';
import { useCategories } from '@/features/catalog/hooks/useCategories';

const SKELETON_ITEMS = 5;

function CategoryChip({ label, selected, onClick }) {
  return (
    <Chip
      label={label}
      clickable
      onClick={onClick}
      aria-pressed={selected}
      icon={selected ? <Check fontSize="small" /> : undefined}
      color={selected ? 'primary' : 'default'}
      variant={selected ? 'filled' : 'outlined'}
    />
  );
}

export function CategoryFilter({ value, onChange }) {
  const categoriesQuery = useCategories();

  return (
    <Stack direction="row" sx={{ flexWrap: 'wrap', gap: 1 }}>
      <CategoryChip label="Tutte" selected={value === null} onClick={() => onChange(null)} />
      {categoriesQuery.isPending
        ? Array.from({ length: SKELETON_ITEMS }, (_, index) => (
            <Skeleton
              key={index}
              variant="rounded"
              aria-hidden="true"
              sx={{ width: (theme) => theme.spacing(11), height: (theme) => theme.spacing(3.5) }}
            />
          ))
        : null}
      {(categoriesQuery.data ?? []).map((category) => (
        <CategoryChip
          key={category.id}
          label={category.name}
          selected={value === category.slug}
          onClick={() => onChange(category.slug)}
        />
      ))}
    </Stack>
  );
}
