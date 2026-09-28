import { http, HttpResponse } from 'msw';
import { buildHealthStatus } from '@/test/factories';

export function apiUrl(path) {
  return `${import.meta.env.VITE_API_URL}${path}`;
}

export const handlers = [
  http.get(apiUrl('/sanctum/csrf-cookie'), () => new HttpResponse(null, { status: 204 })),
  http.get(apiUrl('/api/v1/health'), () => HttpResponse.json(buildHealthStatus())),
];
