import List from '@mui/material/List';
import ListItem from '@mui/material/ListItem';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { parseLightMarkdown } from '@/features/assistant/utils/parseLightMarkdown';

function Segments({ segments }) {
  return segments.map((segment, index) =>
    segment.type === 'bold' ? (
      <strong key={index}>{segment.text}</strong>
    ) : (
      <span key={index}>{segment.text}</span>
    ),
  );
}

function AssistantList({ block, trailing }) {
  const numbered = block.type === 'numbered';

  return (
    <List
      component={numbered ? 'ol' : 'ul'}
      start={numbered ? block.start : undefined}
      disablePadding
      sx={{ pl: 2.5, listStyleType: numbered ? 'decimal' : 'disc' }}
    >
      {block.items.map((segments, index) => (
        <ListItem key={index} disablePadding sx={{ display: 'list-item', py: 0.25 }}>
          <Typography variant="body2" component="span">
            <Segments segments={segments} />
            {index === block.items.length - 1 ? trailing : null}
          </Typography>
        </ListItem>
      ))}
    </List>
  );
}

export function AssistantRichText({ text, trailing = null }) {
  const blocks = parseLightMarkdown(text);

  if (blocks.length === 0) {
    return trailing;
  }

  return (
    <Stack sx={{ gap: 1 }}>
      {blocks.map((block, index) => {
        const blockTrailing = index === blocks.length - 1 ? trailing : null;

        return block.type === 'paragraph' ? (
          <Typography key={index} variant="body2" sx={{ whiteSpace: 'pre-line' }}>
            <Segments segments={block.segments} />
            {blockTrailing}
          </Typography>
        ) : (
          <AssistantList key={index} block={block} trailing={blockTrailing} />
        );
      })}
    </Stack>
  );
}
