import { describe, expect, it } from 'vitest';
import { splitImageFiles } from '@/features/admin/products/imageFiles';

function fileOf(name, type, size) {
  const file = new File(['x'], name, { type });
  Object.defineProperty(file, 'size', { value: size });

  return file;
}

describe('splitImageFiles', () => {
  it('keeps JPG, PNG and WebP up to 5 MB and explains every rejected file', () => {
    const jpg = fileOf('fronte.jpg', 'image/jpeg', 1000);
    const png = fileOf('retro.png', 'image/png', 5 * 1024 * 1024);
    const webp = fileOf('lato.webp', 'image/webp', 2000);

    const result = splitImageFiles([
      jpg,
      fileOf('animata.gif', 'image/gif', 1000),
      png,
      fileOf('enorme.jpg', 'image/jpeg', 5 * 1024 * 1024 + 1),
      webp,
    ]);

    expect(result.accepted).toEqual([jpg, png, webp]);
    expect(result.rejected).toEqual([
      'animata.gif: Formato non supportato: usa JPG, PNG o WebP',
      'enorme.jpg: Il file supera 5 MB',
    ]);
  });
});
