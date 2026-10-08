import axios from 'axios';
import { queryClient } from '@/api/queryClient';
import { endUserSession } from '@/api/session';

let activeQueryClient = queryClient;

export function setActiveQueryClient(client) {
  activeQueryClient = client;
}

export const httpClient = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  withCredentials: true,
  withXSRFToken: true,
  headers: {
    Accept: 'application/json',
  },
});

export function ensureCsrfCookie() {
  return httpClient.get('/sanctum/csrf-cookie');
}

async function handleResponseError(error) {
  const status = error.response?.status;
  const config = error.config;

  if (status === 401) {
    endUserSession(activeQueryClient);
  }

  if (status === 419 && config && !config.csrfRetried) {
    config.csrfRetried = true;
    await ensureCsrfCookie();

    return httpClient(config);
  }

  return Promise.reject(error);
}

httpClient.interceptors.response.use((response) => response, handleResponseError);
