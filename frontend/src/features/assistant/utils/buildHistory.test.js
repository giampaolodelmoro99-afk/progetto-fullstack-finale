import { describe, expect, it } from 'vitest';
import { buildHistory } from '@/features/assistant/utils/buildHistory';

function question(content) {
  return { id: content, role: 'user', content };
}

function answer(content, overrides = {}) {
  return {
    id: content,
    role: 'assistant',
    content,
    products: [],
    interrupted: false,
    failed: false,
    ...overrides,
  };
}

describe('buildHistory', () => {
  it('sends the roles and the raw content, markers included', () => {
    expect(
      buildHistory([
        question('Cuffie?'),
        answer('Le Cuffie Aria [[cuffie-aria]].'),
        question('E per correre?'),
      ]),
    ).toEqual([
      { role: 'user', content: 'Cuffie?' },
      { role: 'assistant', content: 'Le Cuffie Aria [[cuffie-aria]].' },
      { role: 'user', content: 'E per correre?' },
    ]);
  });

  it('keeps only the last 10 messages', () => {
    const messages = Array.from({ length: 12 }, (_, index) =>
      index % 2 === 0 ? question(`domanda ${index}`) : answer(`risposta ${index}`),
    );

    const history = buildHistory(messages);

    expect(history).toHaveLength(10);
    expect(history[0]).toEqual({ role: 'user', content: 'domanda 2' });
    expect(history[9]).toEqual({ role: 'assistant', content: 'risposta 11' });
  });

  it('leaves out interrupted and failed answers', () => {
    expect(
      buildHistory([
        question('Prima'),
        answer('Parziale', { interrupted: true }),
        question('Seconda'),
        answer('Fallita', { failed: true }),
        question('Terza'),
      ]),
    ).toEqual([
      { role: 'user', content: 'Prima' },
      { role: 'user', content: 'Seconda' },
      { role: 'user', content: 'Terza' },
    ]);
  });

  it('truncates an answer to 4000 characters', () => {
    const history = buildHistory([
      question('Prima'),
      answer('a'.repeat(4500)),
      question('Seconda'),
    ]);

    expect(history[1].content).toHaveLength(4000);
  });
});
