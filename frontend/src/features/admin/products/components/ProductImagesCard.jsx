import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useState } from 'react';
import { useFieldArray } from 'react-hook-form';
import { AdminSectionCard } from '@/features/admin/components/AdminSectionCard';
import { ConfirmDialog } from '@/features/admin/components/ConfirmDialog';
import { ImageUploadArea } from '@/features/admin/products/components/ImageUploadArea';
import { ProductImageCard } from '@/features/admin/products/components/ProductImageCard';
import {
  useDeleteProductImage,
  useUploadProductImages,
} from '@/features/admin/products/hooks/useProductImages';
import { splitImageFiles } from '@/features/admin/products/imageFiles';
import { toFormImage } from '@/features/admin/products/schemas';
import { useNotify } from '@/hooks/useNotify';

const INTRO =
  'La prima immagine è la copertina: compare nelle card del negozio e apre la galleria.';

function uploadedMessage(count) {
  return count === 1 ? 'Immagine caricata' : 'Immagini caricate';
}

function createImageSync(form, replace) {
  function syncImages(transform) {
    const defaults = form.formState.defaultValues.images ?? [];
    const current = form.getValues('images');
    form.resetField('images', { defaultValue: transform(defaults) });
    replace(transform(current));
  }

  function addImages(serverImages) {
    const known = new Set(form.getValues('images').map((image) => image.id));
    const added = serverImages.filter((image) => !known.has(image.id)).map(toFormImage);

    if (added.length > 0) {
      syncImages((images) => [...images, ...added]);
    }

    return added.length;
  }

  function removeImage(imageId) {
    syncImages((images) => images.filter((image) => image.id !== imageId));
  }

  return { addImages, removeImage };
}

export function ProductImagesCard({ productId, form }) {
  if (productId === null) {
    return (
      <AdminSectionCard title="Immagini">
        <Stack sx={{ gap: 2 }}>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            {INTRO}
          </Typography>
          <Alert severity="info">Potrai aggiungere le immagini dopo il primo salvataggio.</Alert>
        </Stack>
      </AdminSectionCard>
    );
  }

  return <EditableImages productId={productId} form={form} />;
}

function EditableImages({ productId, form }) {
  const { fields, move, replace } = useFieldArray({
    control: form.control,
    name: 'images',
    keyName: 'key',
  });
  const { addImages, removeImage } = createImageSync(form, replace);
  const uploadImages = useUploadProductImages(productId);
  const deleteImage = useDeleteProductImage(productId);
  const notify = useNotify();
  const [rejected, setRejected] = useState([]);
  const [deleting, setDeleting] = useState(null);

  function handleFiles(files) {
    const { accepted, rejected: rejectedFiles } = splitImageFiles(files);
    setRejected(rejectedFiles);

    if (accepted.length === 0) {
      return;
    }

    let uploaded = 0;
    uploadImages.mutate(
      {
        files: accepted,
        onUploaded: (images) => {
          uploaded += addImages(images);
        },
      },
      {
        onSuccess: () => notify({ message: uploadedMessage(uploaded) }),
      },
    );
  }

  function confirmDelete() {
    deleteImage.mutate(deleting, {
      onSuccess: () => {
        removeImage(deleting.id);
        setDeleting(null);
        notify({ message: 'Immagine eliminata' });
      },
    });
  }

  function closeDelete() {
    setDeleting(null);
    deleteImage.reset();
  }

  return (
    <AdminSectionCard title="Immagini">
      <Stack sx={{ gap: 2 }}>
        <Typography variant="body2" sx={{ color: 'text.secondary' }}>
          {INTRO}
        </Typography>
        <ImageUploadArea onFiles={handleFiles} uploading={uploadImages.isPending} />
        {rejected.length > 0 ? (
          <Alert severity="warning" onClose={() => setRejected([])}>
            <Stack component="ul" sx={{ m: 0, pl: 2 }}>
              {rejected.map((message) => (
                <li key={message}>{message}</li>
              ))}
            </Stack>
          </Alert>
        ) : null}
        {uploadImages.isError ? (
          <Alert severity="error" onClose={() => uploadImages.reset()}>
            {uploadImages.error.message}
          </Alert>
        ) : null}
        {fields.length === 0 ? (
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Non hai ancora aggiunto immagini. Senza immagini il prodotto compare nel negozio con un
            segnaposto.
          </Typography>
        ) : (
          <Box
            component="ol"
            aria-label="Immagini del prodotto"
            sx={{
              listStyle: 'none',
              m: 0,
              p: 0,
              display: 'grid',
              gridTemplateColumns: { xs: '1fr 1fr', md: 'repeat(3, 1fr)' },
              gap: 2,
            }}
          >
            {fields.map((image, index) => (
              <ProductImageCard
                key={image.key}
                control={form.control}
                image={image}
                index={index}
                isLast={index === fields.length - 1}
                onMove={move}
                onDelete={setDeleting}
              />
            ))}
          </Box>
        )}
      </Stack>
      {deleting ? (
        <ConfirmDialog
          title="Eliminare l'immagine?"
          description="L'immagine sparisce subito dal prodotto e dal negozio."
          cancelLabel="Mantieni l'immagine"
          confirmLabel="Elimina l'immagine"
          onConfirm={confirmDelete}
          onClose={closeDelete}
          pending={deleteImage.isPending}
          error={deleteImage.isError ? deleteImage.error.message : null}
        />
      ) : null}
    </AdminSectionCard>
  );
}
