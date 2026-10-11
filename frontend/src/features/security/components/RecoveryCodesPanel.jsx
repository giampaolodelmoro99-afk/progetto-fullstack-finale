import ContentCopyOutlined from '@mui/icons-material/ContentCopyOutlined';
import FileDownloadOutlined from '@mui/icons-material/FileDownloadOutlined';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useCopyToClipboard } from '@/features/security/hooks/useCopyToClipboard';
import { downloadTextFile } from '@/features/security/utils/downloadTextFile';

const FILE_NAME = 'casa-del-silicio-codici-di-recupero.txt';

export function RecoveryCodesPanel({ codes, onDone }) {
  const copy = useCopyToClipboard();
  const text = codes.join('\n');

  return (
    <Stack sx={{ gap: 2 }}>
      <Typography variant="subtitle1" component="h4">
        Salva questi codici in un posto sicuro: non li mostreremo più
      </Typography>
      <Typography variant="body2" sx={{ color: 'text.secondary' }}>
        Ti servono per accedere se non hai con te il telefono o la passkey. Ogni codice si può usare
        una sola volta.
      </Typography>
      <Box
        component="ul"
        aria-label="Codici di recupero"
        sx={{
          display: 'grid',
          gridTemplateColumns: { xs: 'repeat(2, 1fr)', md: 'repeat(4, 1fr)' },
          gap: 1.5,
          m: 0,
          p: 2,
          listStyle: 'none',
          bgcolor: 'background.default',
          borderRadius: 1,
        }}
      >
        {codes.map((code) => (
          <Typography key={code} component="li" variant="code">
            {code}
          </Typography>
        ))}
      </Box>
      <Stack direction={{ xs: 'column', sm: 'row' }} sx={{ gap: 1 }}>
        <Button
          variant="outlined"
          startIcon={<ContentCopyOutlined />}
          onClick={() => copy(text, 'Codici copiati')}
        >
          Copia
        </Button>
        <Button
          variant="outlined"
          startIcon={<FileDownloadOutlined />}
          onClick={() => downloadTextFile(FILE_NAME, `${text}\n`)}
        >
          Scarica
        </Button>
      </Stack>
      <Box>
        <Button variant="contained" onClick={onDone} sx={{ width: { xs: 1, sm: 'auto' } }}>
          Ho salvato i codici
        </Button>
      </Box>
    </Stack>
  );
}
