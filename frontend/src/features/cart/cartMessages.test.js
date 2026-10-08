import { describe, expect, it } from 'vitest';
import {
  checkoutBlockedReason,
  exceedsStockMessage,
  unavailableItemTitle,
} from '@/features/cart/cartMessages';
import { buildCart, buildCartItem } from '@/test/factories';

describe('exceedsStockMessage', () => {
  it('uses the singular with one piece', () => {
    expect(exceedsStockMessage(1)).toBe(
      'È disponibile solo 1 pezzo: riduci la quantità per procedere al checkout.',
    );
  });

  it('uses the plural with more pieces', () => {
    expect(exceedsStockMessage(3)).toBe(
      'Sono disponibili solo 3 pezzi: riduci la quantità per procedere al checkout.',
    );
  });
});

describe('unavailableItemTitle', () => {
  it('calls a sold out product sold out', () => {
    expect(unavailableItemTitle({ status: 'out_of_stock', label: 'Esaurito' })).toBe(
      'Prodotto esaurito',
    );
  });

  it('calls a deactivated or deleted product not available', () => {
    expect(unavailableItemTitle({ status: 'available', label: 'Disponibile' })).toBe(
      'Prodotto non disponibile',
    );
  });
});

describe('checkoutBlockedReason', () => {
  it('asks to remove the unavailable products first', () => {
    const cart = buildCart({
      items: [buildCartItem({ is_purchasable: false }), buildCartItem({ exceeds_stock: true })],
    });

    expect(checkoutBlockedReason(cart)).toBe(
      'Rimuovi i prodotti esauriti per procedere al checkout.',
    );
  });

  it('asks to reduce the quantities when only the stock is short', () => {
    const cart = buildCart({ items: [buildCartItem({ exceeds_stock: true })] });

    expect(checkoutBlockedReason(cart)).toBe(
      'Riduci le quantità indicate per procedere al checkout.',
    );
  });
});
