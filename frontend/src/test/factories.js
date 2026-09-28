export function buildHealthStatus(overrides = {}) {
  return {
    status: 'ok',
    ...overrides,
  };
}
