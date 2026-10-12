import CloudUploadOutlined from '@mui/icons-material/CloudUploadOutlined';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Typography from '@mui/material/Typography';
import { useRef, useState } from 'react';
import { ACCEPTED_IMAGE_TYPES } from '@/features/admin/products/imageFiles';

export function ImageUploadArea({ onFiles, uploading }) {
  const inputRef = useRef(null);
  const [dragging, setDragging] = useState(false);

  function handleDragOver(event) {
    event.preventDefault();
    setDragging(true);
  }

  function handleDrop(event) {
    event.preventDefault();
    setDragging(false);

    if (event.dataTransfer.files.length > 0) {
      onFiles(event.dataTransfer.files);
    }
  }

  function handleChange(event) {
    if (event.target.files.length > 0) {
      onFiles(event.target.files);
    }

    event.target.value = '';
  }

  return (
    <Box
      onDragOver={handleDragOver}
      onDragLeave={() => setDragging(false)}
      onDrop={handleDrop}
      sx={{
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        gap: 1,
        p: 3,
        textAlign: 'center',
        border: 1,
        borderStyle: 'dashed',
        borderColor: dragging ? 'primary.main' : 'divider',
        borderRadius: 1,
        bgcolor: dragging ? 'action.hover' : 'transparent',
      }}
    >
      <CloudUploadOutlined aria-hidden="true" />
      <Typography variant="subtitle2" component="p">
        Trascina qui le immagini
      </Typography>
      <Typography variant="caption" sx={{ color: 'text.secondary' }}>
        JPG, PNG o WebP fino a 5 MB · proporzione 4:5 su fondo neutro
      </Typography>
      <Button
        variant="outlined"
        onClick={() => inputRef.current?.click()}
        loading={uploading}
        sx={{ mt: 1 }}
      >
        Scegli i file
      </Button>
      <Box
        component="input"
        ref={inputRef}
        type="file"
        multiple
        accept={ACCEPTED_IMAGE_TYPES.join(',')}
        onChange={handleChange}
        aria-label="Scegli le immagini da caricare"
        tabIndex={-1}
        sx={{ display: 'none' }}
      />
    </Box>
  );
}
