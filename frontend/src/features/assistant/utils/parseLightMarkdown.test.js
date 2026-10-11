import { describe, expect, it } from 'vitest';
import { parseLightMarkdown, toPlainText } from '@/features/assistant/utils/parseLightMarkdown';

function text(value) {
  return { type: 'text', text: value };
}

function bold(value) {
  return { type: 'bold', text: value };
}

describe('parseLightMarkdown', () => {
  it.each([
    [
      'bold text',
      'Le **Cuffie Aria** costano 349,00 €.',
      [
        {
          type: 'paragraph',
          segments: [text('Le '), bold('Cuffie Aria'), text(' costano 349,00 €.')],
        },
      ],
    ],
    [
      'a bulleted list with dashes',
      '- Cuffie Aria\n- Radio Onda',
      [{ type: 'bulleted', items: [[text('Cuffie Aria')], [text('Radio Onda')]] }],
    ],
    [
      'a bulleted list with asterisks',
      '* Cuffie Aria\n* **Radio Onda**',
      [{ type: 'bulleted', items: [[text('Cuffie Aria')], [bold('Radio Onda')]] }],
    ],
    [
      'a numbered list',
      '1. Apri la pagina\n2. Scegli il prodotto',
      [
        {
          type: 'numbered',
          start: 1,
          items: [[text('Apri la pagina')], [text('Scegli il prodotto')]],
        },
      ],
    ],
    [
      'paragraphs separated by a blank line',
      'Primo paragrafo.\n\nSecondo paragrafo.',
      [
        { type: 'paragraph', segments: [text('Primo paragrafo.')] },
        { type: 'paragraph', segments: [text('Secondo paragrafo.')] },
      ],
    ],
    [
      'a paragraph followed by a list',
      'Ecco due proposte:\n- Cuffie Aria\n- Radio Onda',
      [
        { type: 'paragraph', segments: [text('Ecco due proposte:')] },
        { type: 'bulleted', items: [[text('Cuffie Aria')], [text('Radio Onda')]] },
      ],
    ],
    [
      'an unclosed bold marker while streaming',
      'Ti consiglio le **Cuffie',
      [{ type: 'paragraph', segments: [text('Ti consiglio le Cuffie')] }],
    ],
    ['a heading as plain text', '# Titolo', [{ type: 'paragraph', segments: [text('# Titolo')] }]],
    [
      'a link as plain text',
      'Vedi [qui](https://esempio.it)',
      [{ type: 'paragraph', segments: [text('Vedi [qui](https://esempio.it)')] }],
    ],
    [
      'html as plain text',
      'Un <b>html</b> qui',
      [{ type: 'paragraph', segments: [text('Un <b>html</b> qui')] }],
    ],
  ])('parses %s', (_, input, expected) => {
    expect(parseLightMarkdown(input)).toEqual(expected);
  });

  it('returns no blocks for an empty text', () => {
    expect(parseLightMarkdown('')).toEqual([]);
  });

  it('gives the plain text without the bold markers', () => {
    expect(toPlainText('Le **Cuffie Aria**:\n- 349,00 €\n- Disponibile')).toBe(
      'Le Cuffie Aria:\n349,00 €\nDisponibile',
    );
  });
});
