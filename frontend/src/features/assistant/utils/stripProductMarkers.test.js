import { describe, expect, it } from 'vitest';
import { stripProductMarkers } from '@/features/assistant/utils/stripProductMarkers';

describe('stripProductMarkers', () => {
  it.each([
    ['Ti consiglio le Cuffie Aria [[cuffie-aria]].', 'Ti consiglio le Cuffie Aria.'],
    [
      'Le Cuffie Aria [[cuffie-aria]] e la Lampada Faro [[lampada-faro]] sono disponibili.',
      'Le Cuffie Aria e la Lampada Faro sono disponibili.',
    ],
    ['Ti consiglio le Cuffie Aria [[cuf', 'Ti consiglio le Cuffie Aria'],
    ['Ti consiglio le Cuffie Aria [[', 'Ti consiglio le Cuffie Aria'],
    ['Ti consiglio le Cuffie Aria [', 'Ti consiglio le Cuffie Aria'],
    ['Ti consiglio le Cuffie Aria [[cuffie-aria]', 'Ti consiglio le Cuffie Aria'],
    ['Nessun marcatore qui.', 'Nessun marcatore qui.'],
    ['- Cuffie Aria [[cuffie-aria]]\n- Radio Onda [[radio-onda]]', '- Cuffie Aria\n- Radio Onda'],
  ])('turns %j into %j', (text, expected) => {
    expect(stripProductMarkers(text)).toBe(expected);
  });
});
