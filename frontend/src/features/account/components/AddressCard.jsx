import DeleteOutlined from '@mui/icons-material/DeleteOutlined';
import EditOutlined from '@mui/icons-material/EditOutlined';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Chip from '@mui/material/Chip';
import Divider from '@mui/material/Divider';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { formatPostalLine } from '@/utils/format';

export function AddressCard({ address, onEdit, onDelete, onMakeDefault, makingDefault }) {
  const place = `${address.street}, ${address.city}`;

  return (
    <Card
      component="article"
      aria-label={`Indirizzo ${place}`}
      sx={{
        height: 1,
        display: 'flex',
        flexDirection: 'column',
        borderWidth: address.is_default ? 2 : 1,
        borderColor: address.is_default ? 'primary.main' : 'divider',
      }}
    >
      <CardContent sx={{ display: 'flex', flexDirection: 'column', gap: 2, flexGrow: 1 }}>
        <Stack sx={{ gap: 0.5, flexGrow: 1 }}>
          <Stack direction="row" sx={{ gap: 1, alignItems: 'center', flexWrap: 'wrap' }}>
            <Typography variant="subtitle2" component="h3">
              {address.full_name}
            </Typography>
            {address.is_default ? <Chip label="Predefinito" size="small" color="primary" /> : null}
          </Stack>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            {address.street}
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            {formatPostalLine(address)}
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            {address.phone}
          </Typography>
        </Stack>
        <Divider />
        <Stack direction="row" sx={{ gap: 0.5, flexWrap: 'wrap', mx: -2 }}>
          <Button
            size="small"
            startIcon={<EditOutlined />}
            onClick={() => onEdit(address)}
            aria-label={`Modifica l'indirizzo ${place}`}
          >
            Modifica
          </Button>
          <Button
            size="small"
            startIcon={<DeleteOutlined />}
            onClick={() => onDelete(address)}
            aria-label={`Elimina l'indirizzo ${place}`}
          >
            Elimina
          </Button>
          {address.is_default ? null : (
            <Button
              size="small"
              onClick={() => onMakeDefault(address)}
              loading={makingDefault}
              aria-label={`Imposta come predefinito l'indirizzo ${place}`}
            >
              Imposta come predefinito
            </Button>
          )}
        </Stack>
      </CardContent>
    </Card>
  );
}
