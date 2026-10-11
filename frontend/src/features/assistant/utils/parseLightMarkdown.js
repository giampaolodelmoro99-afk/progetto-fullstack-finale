const BULLET_ITEM = /^\s*[-*]\s+(.*)$/;

const NUMBERED_ITEM = /^\s*(\d+)\.\s+(.*)$/;

const BOLD = /\*\*(.+?)\*\*/g;

function plainSegment(text) {
  return { type: 'text', text: text.replaceAll('**', '') };
}

export function parseInline(text) {
  const segments = [];
  let lastIndex = 0;

  for (const match of text.matchAll(BOLD)) {
    if (match.index > lastIndex) {
      segments.push(plainSegment(text.slice(lastIndex, match.index)));
    }

    segments.push({ type: 'bold', text: match[1] });
    lastIndex = match.index + match[0].length;
  }

  if (lastIndex < text.length) {
    segments.push(plainSegment(text.slice(lastIndex)));
  }

  return segments.filter((segment) => segment.text !== '');
}

export function parseLightMarkdown(text) {
  const blocks = [];
  let current = null;

  function close() {
    if (current !== null) {
      blocks.push(
        current.type === 'paragraph'
          ? { type: 'paragraph', segments: parseInline(current.lines.join('\n')) }
          : current,
      );
    }

    current = null;
  }

  function addItem(type, content, start) {
    if (current?.type !== type) {
      close();
      current = type === 'numbered' ? { type, start, items: [] } : { type, items: [] };
    }

    current.items.push(parseInline(content));
  }

  for (const line of text.split('\n')) {
    const bullet = line.match(BULLET_ITEM);
    const numbered = line.match(NUMBERED_ITEM);

    if (bullet) {
      addItem('bulleted', bullet[1]);
    } else if (numbered) {
      addItem('numbered', numbered[2], Number(numbered[1]));
    } else if (line.trim() === '') {
      close();
    } else {
      if (current?.type !== 'paragraph') {
        close();
        current = { type: 'paragraph', lines: [] };
      }

      current.lines.push(line.trim());
    }
  }

  close();

  return blocks.filter((block) =>
    block.type === 'paragraph' ? block.segments.length > 0 : block.items.length > 0,
  );
}

function segmentsText(segments) {
  return segments.map((segment) => segment.text).join('');
}

export function toPlainText(text) {
  return parseLightMarkdown(text)
    .map((block) =>
      block.type === 'paragraph'
        ? segmentsText(block.segments)
        : block.items.map(segmentsText).join('\n'),
    )
    .join('\n');
}
