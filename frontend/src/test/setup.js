import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterAll, afterEach, beforeAll } from 'vitest';
import { queryClient } from '@/api/queryClient';
import { server } from '@/test/mocks/server';
import { installMatchMedia, resetViewport } from '@/test/viewport';

installMatchMedia();

window.scrollTo = () => {};
Element.prototype.scrollIntoView = () => {};

beforeAll(() => {
  server.listen({ onUnhandledRequest: 'error' });
});

afterEach(() => {
  cleanup();
  server.resetHandlers();
  queryClient.clear();
  resetViewport();
});

afterAll(() => {
  server.close();
});
