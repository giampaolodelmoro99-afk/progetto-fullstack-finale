import axios from 'axios';
import { queryClient } from '@/api/queryClient';
import { endUserSession } from '@/api/session';

let activeQueryClient = queryClient;
let identityConfirmationHandler = null;

export function setActiveQueryClient(client) {
  activeQueryClient = client;
}

export function setIdentityConfirmationHandler(handler) {
  identityConfirmationHandler = handler;
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

async function confirmIdentityAndRetry(error, config) {
  config.identityRetried = true;

  try {
    await identityConfirmationHandler({ prompt: config.identityPrompt ?? null });
  } catch {
    error.identityConfirmationCancelled = true;

    return Promise.reject(error);
  }

  return httpClient(config);
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

  if (status === 423 && config && !config.identityRetried && identityConfirmationHandler) {
    return confirmIdentityAndRetry(error, config);
  }

  return Promise.reject(error);
}

httpClient.interceptors.response.use((response) => response, handleResponseError);
