import audio from '@/assets/categories/audio.webp';
import charging from '@/assets/categories/charging.webp';
import desk from '@/assets/categories/desk.webp';
import smartHome from '@/assets/categories/smart-home.webp';
import travel from '@/assets/categories/travel.webp';
import wearables from '@/assets/categories/wearables.webp';
import fallback from '@/assets/fallback.webp';

export const categoryImages = {
  audio,
  'ricarica-ed-energia': charging,
  scrivania: desk,
  indossabili: wearables,
  'casa-smart': smartHome,
  viaggio: travel,
};

export function getCategoryImage(slug) {
  return categoryImages[slug] ?? fallback;
}
