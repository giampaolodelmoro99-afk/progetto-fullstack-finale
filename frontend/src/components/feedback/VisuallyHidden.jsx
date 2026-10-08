import Box from '@mui/material/Box';

export function VisuallyHidden({ children }) {
  return (
    <Box component="span" sx={(theme) => theme.mixins.visuallyHidden}>
      {children}
    </Box>
  );
}
