export function exceedsStockMessage(available) {
  return available === 1
    ? 'È disponibile solo 1 pezzo: riduci la quantità per procedere al checkout.'
    : `Sono disponibili solo ${available} pezzi: riduci la quantità per procedere al checkout.`;
}

export function unavailableItemTitle(availability) {
  return availability.status === 'out_of_stock' ? 'Prodotto esaurito' : 'Prodotto non disponibile';
}

export const UNAVAILABLE_ITEM_MESSAGE =
  'Questo prodotto non è più disponibile. Rimuovilo dal carrello per procedere al checkout.';

export function checkoutBlockedReason(cart) {
  if (cart.items.some((item) => !item.is_purchasable)) {
    return 'Rimuovi i prodotti esauriti per procedere al checkout.';
  }

  if (cart.items.some((item) => item.exceeds_stock)) {
    return 'Riduci le quantità indicate per procedere al checkout.';
  }

  return null;
}
