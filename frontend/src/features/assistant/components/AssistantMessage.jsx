import Box from '@mui/material/Box';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { VisuallyHidden } from '@/components/feedback/VisuallyHidden';
import { STOPPED_MESSAGE } from '@/features/assistant/assistantConstants';
import { AssistantProductCard } from '@/features/assistant/components/AssistantProductCard';
import { AssistantRichText } from '@/features/assistant/components/AssistantRichText';
import { stripProductMarkers } from '@/features/assistant/utils/stripProductMarkers';

function Cursor() {
  return (
    <Box
      component="span"
      aria-hidden="true"
      sx={(theme) => ({
        display: 'inline-block',
        width: theme.spacing(0.25),
        height: '1em',
        ml: 0.25,
        verticalAlign: 'text-bottom',
        bgcolor: 'text.primary',
      })}
    />
  );
}

function UserMessage({ message }) {
  return (
    <Box
      sx={{
        alignSelf: 'flex-end',
        maxWidth: '85%',
        px: 1.5,
        py: 1,
        borderRadius: 1,
        bgcolor: 'text.primary',
        color: 'background.paper',
      }}
    >
      <Typography variant="body2" sx={{ whiteSpace: 'pre-wrap', overflowWrap: 'anywhere' }}>
        <VisuallyHidden>Tu: </VisuallyHidden>
        {message.content}
      </Typography>
    </Box>
  );
}

export function AssistantMessage({ message, streaming = false }) {
  if (message.role === 'user') {
    return <UserMessage message={message} />;
  }

  const text = stripProductMarkers(message.content);
  const showBubble = text.trim() !== '' || streaming;
  const products = message.interrupted ? [] : message.products;

  return (
    <Stack sx={{ alignItems: 'flex-start', gap: 1 }}>
      {showBubble ? (
        <>
          <Typography variant="caption" sx={{ color: 'text.secondary' }}>
            Assistente
          </Typography>
          <Box
            sx={{
              maxWidth: '85%',
              px: 1.5,
              py: 1,
              borderRadius: 1,
              border: 1,
              borderColor: 'divider',
              bgcolor: 'background.paper',
              overflowWrap: 'anywhere',
            }}
          >
            <AssistantRichText text={text} trailing={streaming ? <Cursor /> : null} />
          </Box>
        </>
      ) : null}
      {message.interrupted ? (
        <Typography variant="caption" sx={{ color: 'text.secondary' }}>
          {STOPPED_MESSAGE}
        </Typography>
      ) : null}
      {products.length > 0 ? (
        <Stack
          component="ul"
          aria-label="Prodotti citati"
          sx={{ width: 1, gap: 1, m: 0, p: 0, listStyle: 'none' }}
        >
          {products.map((product) => (
            <li key={product.id}>
              <AssistantProductCard product={product} />
            </li>
          ))}
        </Stack>
      ) : null}
    </Stack>
  );
}
