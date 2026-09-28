import { createTheme } from '@mui/material/styles';
import { components } from '@/theme/components';
import { palette } from '@/theme/palette';
import { typography } from '@/theme/typography';

export const theme = createTheme({
  palette,
  typography,
  components,
  shape: {
    borderRadius: 8,
  },
});
