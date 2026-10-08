import { itIT } from '@mui/material/locale';
import { createTheme } from '@mui/material/styles';
import { components, localizedComponents } from '@/theme/components';
import { palette } from '@/theme/palette';
import { typography } from '@/theme/typography';

const shadows = [
  'none',
  '0 1px 2px #1C1B190F, 0 2px 8px #1C1B190A',
  ...Array(7).fill('0 2px 4px #1C1B190F, 0 8px 24px #1C1B1914'),
  ...Array(16).fill('0 4px 8px #1C1B1914, 0 24px 48px #1C1B191F'),
];

export const layout = {
  minPageHeight: '100vh',
  adminDrawerWidth: 264,
  storeDrawerWidth: 320,
  drawerMaxWidth: '85vw',
  emptyStateTextMaxWidth: '48ch',
  footerTaglineMaxWidth: '32ch',
  navigationUnderlineOffset: 6,
  authCardMaxWidth: 480,
  accountNavigationWidth: 260,
  assistantPanelWidth: 440,
  assistantPanelMaxHeight: 'calc(100vh - 160px)',
  heroTextMaxWidth: '48ch',
  anchorScrollOffset: 160,
  mobileActionBarHeight: 80,
  stickySummaryTop: 120,
};

const mixins = {
  visuallyHidden: {
    border: 0,
    clip: 'rect(0 0 0 0)',
    height: '1px',
    margin: '-1px',
    overflow: 'hidden',
    padding: 0,
    position: 'absolute',
    whiteSpace: 'nowrap',
    width: '1px',
  },
};

export const theme = createTheme(
  {
    spacing: 8,
    mixins,
    shape: { borderRadius: 4 },
    shadows,
    palette,
    typography,
    components,
    layout,
  },
  itIT,
  { components: localizedComponents },
);
