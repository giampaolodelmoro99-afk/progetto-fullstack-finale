import { afterEach, describe, expect, it, vi } from 'vitest';
import { httpClient } from '@/api/httpClient';
import { uploadProductImages } from '@/features/admin/products/api/productsApi';

describe('uploadProductImages', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('sends every file as images[] in a multipart body and returns the images', async () => {
    const images = [{ id: 1, url: 'https://cdn.esempio.it/products/1.webp', alt: 'Cuffie Aria' }];
    const post = vi.spyOn(httpClient, 'post').mockResolvedValue({ data: { data: images } });
    const front = new File(['a'], 'fronte.jpg', { type: 'image/jpeg' });
    const back = new File(['b'], 'retro.png', { type: 'image/png' });

    await expect(uploadProductImages(9, [front, back])).resolves.toEqual(images);

    const [url, body] = post.mock.calls[0];
    expect(url).toBe('/api/v1/admin/products/9/images');
    expect(body).toBeInstanceOf(FormData);
    expect(body.getAll('images[]').map((file) => file.name)).toEqual(['fronte.jpg', 'retro.png']);
  });

  it('normalizes the error of the server', async () => {
    vi.spyOn(httpClient, 'post').mockRejectedValue({
      response: {
        status: 422,
        data: { message: 'Il file supera 5 MB.', errors: { 'images.0': ['Il file supera 5 MB.'] } },
        headers: {},
      },
    });

    await expect(uploadProductImages(9, [])).rejects.toMatchObject({
      status: 422,
      message: 'Il file supera 5 MB.',
    });
  });
});
