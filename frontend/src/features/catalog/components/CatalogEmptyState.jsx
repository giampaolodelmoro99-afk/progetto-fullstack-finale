import SearchOutlined from '@mui/icons-material/SearchOutlined';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import { EmptyState } from '@/components/feedback/EmptyState';

export function CatalogEmptyState({ onClear }) {
  return (
    <Card>
      <EmptyState
        icon={<SearchOutlined />}
        title="Nessun prodotto corrisponde ai filtri"
        description="Prova ad ampliare la fascia di prezzo o a disattivare «Solo disponibili»."
        action={
          <Button variant="outlined" onClick={onClear}>
            Azzera filtri
          </Button>
        }
      />
    </Card>
  );
}
