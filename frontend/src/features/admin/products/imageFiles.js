export const ACCEPTED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

export const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

export function splitImageFiles(files) {
  const accepted = [];
  const rejected = [];

  Array.from(files).forEach((file) => {
    if (!ACCEPTED_IMAGE_TYPES.includes(file.type)) {
      rejected.push(`${file.name}: Formato non supportato: usa JPG, PNG o WebP`);
    } else if (file.size > MAX_IMAGE_BYTES) {
      rejected.push(`${file.name}: Il file supera 5 MB`);
    } else {
      accepted.push(file);
    }
  });

  return { accepted, rejected };
}
