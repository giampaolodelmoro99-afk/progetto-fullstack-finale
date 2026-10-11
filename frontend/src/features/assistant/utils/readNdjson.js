function parseLine(line) {
  return line.trim() === '' ? null : JSON.parse(line);
}

export async function* readNdjson(stream, signal) {
  const reader = stream.getReader();
  const decoder = new TextDecoder();
  const cancel = () => reader.cancel().catch(() => {});
  let buffer = '';

  signal?.addEventListener('abort', cancel);

  try {
    while (true) {
      const { value, done } = await reader.read();

      if (done) {
        break;
      }

      buffer += decoder.decode(value, { stream: true });
      const lines = buffer.split('\n');
      buffer = lines.pop();

      for (const line of lines) {
        const event = parseLine(line);

        if (event !== null) {
          yield event;
        }
      }
    }

    const last = parseLine(buffer + decoder.decode());

    if (last !== null) {
      yield last;
    }
  } finally {
    signal?.removeEventListener('abort', cancel);
    reader.releaseLock();
  }
}
