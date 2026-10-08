import { describe, expect, it } from 'vitest';
import { getCategoryImage } from '@/features/catalog/categoryImages';

describe('category images', () => {
  it.each([
    ['audio', 'audio.webp'],
    ['ricarica-ed-energia', 'charging.webp'],
    ['scrivania', 'desk.webp'],
    ['indossabili', 'wearables.webp'],
    ['casa-smart', 'smart-home.webp'],
    ['viaggio', 'travel.webp'],
  ])('maps the category %s to its illustration', (slug, file) => {
    expect(getCategoryImage(slug)).toMatch(new RegExp(`${file}$`));
  });

  it('falls back to the neutral image for an unknown category', () => {
    expect(getCategoryImage('giardino')).toMatch(/fallback\.webp$/);
  });
});
