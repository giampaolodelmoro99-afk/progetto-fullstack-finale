const COMPLETE_MARKERS = /[ \t]*\[\[[^[\]\n]*\]\]/g;

const TRAILING_PARTIAL_MARKER = /[ \t]*\[(\[[^[\]\n]*\]?)?$/;

export function stripProductMarkers(text) {
  return text.replace(COMPLETE_MARKERS, '').replace(TRAILING_PARTIAL_MARKER, '');
}
