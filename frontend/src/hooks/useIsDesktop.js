import useMediaQuery from '@mui/material/useMediaQuery';

export function useIsDesktop() {
  return useMediaQuery((theme) => theme.breakpoints.up('md'), { noSsr: true });
}
