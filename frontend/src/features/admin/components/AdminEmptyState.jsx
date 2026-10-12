import Button from '@mui/material/Button';
import { EmptyState } from '@/components/feedback/EmptyState';

export function AdminEmptyState({ icon, title, description, onReset }) {
  return (
    <EmptyState
      icon={icon}
      title={title}
      titleComponent="h3"
      description={description}
      action={
        <Button variant="outlined" onClick={onReset}>
          Azzera filtri
        </Button>
      }
    />
  );
}
