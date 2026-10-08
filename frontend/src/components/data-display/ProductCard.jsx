import Card from '@mui/material/Card';
import CardActionArea from '@mui/material/CardActionArea';
import CardContent from '@mui/material/CardContent';
import CardMedia from '@mui/material/CardMedia';
import Chip from '@mui/material/Chip';
import Typography from '@mui/material/Typography';
import { Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';
import fallbackImage from '@/assets/fallback.webp';
import { availabilityColors } from '@/theme/statusColors';
import { formatPrice } from '@/utils/format';

export function ProductCard({ product, headingComponent = 'h3' }) {
  const { availability } = product;

  return (
    <Card sx={{ height: 1, display: 'flex', flexDirection: 'column', position: 'relative' }}>
      <CardActionArea
        component={RouterLink}
        to={paths.product(product.slug)}
        sx={{ flexGrow: 1, display: 'flex', flexDirection: 'column', alignItems: 'stretch' }}
      >
        <CardMedia
          component="img"
          image={product.cover?.url ?? fallbackImage}
          alt={product.cover?.alt ?? product.name}
          loading="lazy"
        />
        {product.is_new ? (
          <Chip
            label="Novità"
            size="small"
            variant="outlined"
            sx={{
              position: 'absolute',
              top: (theme) => theme.spacing(1.5),
              left: (theme) => theme.spacing(1.5),
              bgcolor: 'background.paper',
            }}
          />
        ) : null}
        <CardContent sx={{ flexGrow: 1 }}>
          <Typography variant="overline" component="p" sx={{ color: 'text.secondary' }}>
            {product.category.name}
          </Typography>
          <Typography variant="subtitle1" component={headingComponent}>
            {product.name}
          </Typography>
          <Typography variant="price" component="p" sx={{ mt: 1 }}>
            {formatPrice(product.price)}
          </Typography>
          {availability.status === 'available' ? null : (
            <Typography
              variant="captionStrong"
              component="p"
              sx={{ color: availabilityColors[availability.status], mt: 0.5 }}
            >
              {availability.label}
            </Typography>
          )}
        </CardContent>
      </CardActionArea>
    </Card>
  );
}
