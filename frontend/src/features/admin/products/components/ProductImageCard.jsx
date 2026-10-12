import ArrowBack from '@mui/icons-material/ArrowBack';
import ArrowForward from '@mui/icons-material/ArrowForward';
import DeleteOutlined from '@mui/icons-material/DeleteOutlined';
import Star from '@mui/icons-material/Star';
import Box from '@mui/material/Box';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import CardMedia from '@mui/material/CardMedia';
import Chip from '@mui/material/Chip';
import IconButton from '@mui/material/IconButton';
import Stack from '@mui/material/Stack';
import { FormTextField } from '@/components/form/FormTextField';

export function ProductImageCard({ control, image, index, isLast, onMove, onDelete }) {
  const isCover = index === 0;

  return (
    <Card
      component="li"
      role="group"
      aria-label={`Immagine ${index + 1}`}
      sx={{
        position: 'relative',
        borderColor: isCover ? 'primary.main' : 'divider',
        borderWidth: isCover ? 2 : 1,
      }}
    >
      {isCover ? (
        <Chip
          size="small"
          icon={<Star />}
          label="Copertina"
          color="primary"
          sx={{
            position: 'absolute',
            top: (theme) => theme.spacing(1),
            left: (theme) => theme.spacing(1),
          }}
        />
      ) : null}
      <CardMedia component="img" image={image.url} alt={image.alt} />
      <CardContent sx={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
        <FormTextField
          name={`images.${index}.alt`}
          control={control}
          label="Testo alternativo"
          size="small"
          fullWidth
          slotProps={{ htmlInput: { maxLength: 255 } }}
        />
        <Stack direction="row" sx={{ alignItems: 'center' }}>
          <IconButton
            aria-label="Sposta prima"
            disabled={index === 0}
            onClick={() => onMove(index, index - 1)}
          >
            <ArrowBack />
          </IconButton>
          <IconButton
            aria-label="Sposta dopo"
            disabled={isLast}
            onClick={() => onMove(index, index + 1)}
          >
            <ArrowForward />
          </IconButton>
          <Box sx={{ flexGrow: 1 }} />
          <IconButton aria-label="Elimina l'immagine" onClick={() => onDelete(image)}>
            <DeleteOutlined />
          </IconButton>
        </Stack>
      </CardContent>
    </Card>
  );
}
