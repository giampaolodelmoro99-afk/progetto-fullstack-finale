import Add from '@mui/icons-material/Add';
import ArrowDownward from '@mui/icons-material/ArrowDownward';
import ArrowUpward from '@mui/icons-material/ArrowUpward';
import DeleteOutlined from '@mui/icons-material/DeleteOutlined';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import IconButton from '@mui/material/IconButton';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useFieldArray } from 'react-hook-form';
import { FormTextField } from '@/components/form/FormTextField';
import { AdminSectionCard } from '@/features/admin/components/AdminSectionCard';
import { MAX_SPECIFICATIONS } from '@/features/admin/products/schemas';

const ROW_COLUMNS = { xs: '1fr', md: '2fr 3fr auto' };

export function SpecificationsCard({ control }) {
  const { fields, append, move, remove } = useFieldArray({ control, name: 'specifications' });
  const last = fields.length - 1;

  return (
    <AdminSectionCard title="Scheda tecnica">
      <Stack sx={{ gap: 2 }}>
        <Typography variant="body2" sx={{ color: 'text.secondary' }}>
          Le righe compaiono nella scheda prodotto nell&apos;ordine indicato qui.
        </Typography>
        {fields.length > 0 ? (
          <Box
            sx={{
              display: { xs: 'none', md: 'grid' },
              gridTemplateColumns: ROW_COLUMNS,
              gap: 1.5,
              typography: 'body2',
              color: 'text.secondary',
            }}
          >
            <span>Etichetta</span>
            <span>Valore</span>
          </Box>
        ) : null}
        <Stack component="ol" sx={{ listStyle: 'none', m: 0, p: 0, gap: 1.5 }}>
          {fields.map((field, index) => (
            <Box
              key={field.id}
              component="li"
              aria-label={`Riga ${index + 1}`}
              sx={{
                display: 'grid',
                gridTemplateColumns: ROW_COLUMNS,
                gap: 1.5,
                alignItems: 'flex-start',
              }}
            >
              <FormTextField
                name={`specifications.${index}.label`}
                control={control}
                label="Etichetta"
                size="small"
                fullWidth
                slotProps={{ htmlInput: { maxLength: 64 } }}
              />
              <FormTextField
                name={`specifications.${index}.value`}
                control={control}
                label="Valore"
                size="small"
                fullWidth
                slotProps={{ htmlInput: { maxLength: 255 } }}
              />
              <Stack direction="row" sx={{ justifyContent: 'flex-end' }}>
                <IconButton
                  aria-label="Sposta su"
                  disabled={index === 0}
                  onClick={() => move(index, index - 1)}
                >
                  <ArrowUpward />
                </IconButton>
                <IconButton
                  aria-label="Sposta giù"
                  disabled={index === last}
                  onClick={() => move(index, index + 1)}
                >
                  <ArrowDownward />
                </IconButton>
                <IconButton aria-label="Elimina la riga" onClick={() => remove(index)}>
                  <DeleteOutlined />
                </IconButton>
              </Stack>
            </Box>
          ))}
        </Stack>
        <Box>
          <Button
            startIcon={<Add />}
            disabled={fields.length >= MAX_SPECIFICATIONS}
            onClick={() => append({ label: '', value: '' })}
          >
            Aggiungi una riga
          </Button>
        </Box>
      </Stack>
    </AdminSectionCard>
  );
}
