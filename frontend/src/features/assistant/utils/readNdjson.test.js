import { describe, expect, it } from 'vitest';
import { readNdjson } from '@/features/assistant/utils/readNdjson';

function streamOf(chunks) {
  const encoder = new TextEncoder();

  return new ReadableStream({
    start(controller) {
      chunks.forEach((chunk) => controller.enqueue(encoder.encode(chunk)));
      controller.close();
    },
  });
}

async function collect(stream) {
  const events = [];

  for await (const event of readNdjson(stream)) {
    events.push(event);
  }

  return events;
}

describe('readNdjson', () => {
  it('reads one event per line, also when a line is split between chunks', async () => {
    const events = await collect(
      streamOf([
        '{"type":"text","delta":"Ciao"}\n{"type":"te',
        'xt","delta":" a te"}\n',
        '{"type":"done"}\n',
      ]),
    );

    expect(events).toEqual([
      { type: 'text', delta: 'Ciao' },
      { type: 'text', delta: ' a te' },
      { type: 'done' },
    ]);
  });

  it('reads a last line without the final newline and skips empty lines', async () => {
    expect(await collect(streamOf(['\n{"type":"text","delta":"è"}\n\n{"type":"done"}']))).toEqual([
      { type: 'text', delta: 'è' },
      { type: 'done' },
    ]);
  });

  it('decodes a character split between chunks', async () => {
    const bytes = new TextEncoder().encode('{"type":"text","delta":"€"}\n');
    const stream = new ReadableStream({
      start(controller) {
        controller.enqueue(bytes.slice(0, 26));
        controller.enqueue(bytes.slice(26));
        controller.close();
      },
    });

    expect(await collect(stream)).toEqual([{ type: 'text', delta: '€' }]);
  });
});
