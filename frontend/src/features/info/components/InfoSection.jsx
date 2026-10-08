import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';

export function InfoSection({ id, title, paragraphs }) {
  return (
    <Stack
      component="section"
      id={id}
      aria-labelledby={`${id}-title`}
      sx={{ gap: 1.5, scrollMarginTop: (theme) => theme.layout.anchorScrollOffset }}
    >
      <Typography id={`${id}-title`} variant="h4" component="h2">
        {title}
      </Typography>
      {paragraphs.map((paragraph, index) => (
        <Typography key={index} variant="body1">
          {paragraph}
        </Typography>
      ))}
    </Stack>
  );
}
