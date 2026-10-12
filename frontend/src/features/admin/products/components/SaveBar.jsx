import Button from '@mui/material/Button';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';

function negate(spacing) {
  return Object.fromEntries(Object.entries(spacing).map(([key, value]) => [key, -value]));
}

export function SaveBar({ isDirty, saving, cancelDisabled, onCancel }) {
  return (
    <Stack
      direction="row"
      sx={(theme) => ({
        position: 'sticky',
        bottom: 0,
        zIndex: 'appBar',
        mx: negate(theme.layout.adminContentPadding),
        mb: negate(theme.layout.adminContentPadding),
        px: theme.layout.adminContentPadding,
        py: 1.5,
        gap: 1.5,
        alignItems: 'center',
        bgcolor: 'background.paper',
        borderTop: 1,
        borderColor: 'divider',
      })}
    >
      <Typography
        variant="body2"
        role="status"
        sx={{ color: 'text.secondary', flexGrow: 1, minWidth: 0 }}
      >
        {isDirty ? 'Hai modifiche non salvate' : ''}
      </Typography>
      <Button variant="outlined" onClick={onCancel} disabled={cancelDisabled || saving}>
        Annulla
      </Button>
      <Button type="submit" variant="contained" loading={saving}>
        Salva
      </Button>
    </Stack>
  );
}
