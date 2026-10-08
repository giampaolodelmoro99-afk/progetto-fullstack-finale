export const DESKTOP_WIDTH = 1280;
export const MOBILE_WIDTH = 390;

let currentWidth = DESKTOP_WIDTH;
const mediaQueryLists = new Set();

function evaluate(query, width) {
  const conditions = [...query.matchAll(/\((min|max)-width:\s*([\d.]+)px\)/g)];

  return conditions.every(([, kind, value]) =>
    kind === 'min' ? width >= Number(value) : width <= Number(value),
  );
}

function createMediaQueryList(query) {
  const listeners = new Set();
  const mediaQueryList = {
    media: query,
    get matches() {
      return evaluate(query, currentWidth);
    },
    onchange: null,
    addEventListener: (type, listener) => listeners.add(listener),
    removeEventListener: (type, listener) => listeners.delete(listener),
    addListener: (listener) => listeners.add(listener),
    removeListener: (listener) => listeners.delete(listener),
    dispatchEvent: () => true,
    notify: () => {
      listeners.forEach((listener) => listener({ matches: mediaQueryList.matches, media: query }));
    },
  };
  mediaQueryLists.add(mediaQueryList);

  return mediaQueryList;
}

export function installMatchMedia() {
  window.matchMedia = (query) => createMediaQueryList(query);
}

export function setViewportWidth(width) {
  currentWidth = width;
  mediaQueryLists.forEach((mediaQueryList) => mediaQueryList.notify());
}

export function resetViewport() {
  currentWidth = DESKTOP_WIDTH;
  mediaQueryLists.clear();
}
