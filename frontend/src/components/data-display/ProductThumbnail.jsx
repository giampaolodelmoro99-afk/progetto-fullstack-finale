import Box from '@mui/material/Box';
import fallbackImage from '@/assets/fallback.webp';

const SIZES = {
  small: { xs: 6, md: 7 },
  large: { xs: 10, md: 13 },
};

export function ProductThumbnail({ cover, name, size = 'small' }) {
  const spacing = SIZES[size];

  return (
    <Box
      component="img"
      src={cover?.url ?? fallbackImage}
      alt={cover?.alt ?? name}
      loading="lazy"
      sx={(theme) => ({
        width: { xs: theme.spacing(spacing.xs), md: theme.spacing(spacing.md) },
        aspectRatio: '1 / 1',
        flexShrink: 0,
        objectFit: 'contain',
        bgcolor: 'background.media',
        borderRadius: 1,
      })}
    />
  );
}
