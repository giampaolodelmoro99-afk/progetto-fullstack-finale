import Box from '@mui/material/Box';
import ButtonBase from '@mui/material/ButtonBase';
import Stack from '@mui/material/Stack';
import { useState } from 'react';
import fallbackImage from '@/assets/fallback.webp';

export function ProductGallery({ product }) {
  const images =
    product.images.length > 0 ? product.images : [{ url: fallbackImage, alt: product.name }];
  const [selectedIndex, setSelectedIndex] = useState(0);
  const selected = images[Math.min(selectedIndex, images.length - 1)];

  return (
    <Stack direction={{ xs: 'column-reverse', md: 'row' }} sx={{ gap: 2 }}>
      {images.length > 1 ? (
        <Stack
          component="ul"
          aria-label="Immagini del prodotto"
          direction={{ xs: 'row', md: 'column' }}
          sx={{ listStyle: 'none', m: 0, p: 0, gap: 1.5, flexShrink: 0 }}
        >
          {images.map((image, index) => (
            <li key={image.url}>
              <ButtonBase
                aria-label={`Mostra l'immagine ${index + 1}`}
                aria-pressed={index === selectedIndex}
                onClick={() => setSelectedIndex(index)}
                sx={{
                  width: (theme) => theme.spacing(9),
                  aspectRatio: '1 / 1',
                  borderRadius: 1,
                  overflow: 'hidden',
                  border: 1,
                  borderColor: index === selectedIndex ? 'text.primary' : 'divider',
                  bgcolor: 'background.media',
                }}
              >
                <Box
                  component="img"
                  src={image.url}
                  alt=""
                  sx={{ width: 1, height: 1, objectFit: 'contain', display: 'block' }}
                />
              </ButtonBase>
            </li>
          ))}
        </Stack>
      ) : null}
      <Box
        component="img"
        src={selected.url}
        alt={selected.alt}
        sx={{
          flexGrow: 1,
          minWidth: 0,
          width: 1,
          aspectRatio: '4 / 5',
          objectFit: 'contain',
          display: 'block',
          borderRadius: 2,
          bgcolor: 'background.media',
        }}
      />
    </Stack>
  );
}
