import { ensureCsrfCookie, httpClient } from '@/api/httpClient';
import {
  INTERRUPTED_ANSWER_MESSAGE,
  UNAVAILABLE_MESSAGE,
} from '@/features/assistant/assistantConstants';
import { readNdjson } from '@/features/assistant/utils/readNdjson';
import { createClientError, normalizeApiError } from '@/utils/normalizeApiError';

export const NETWORK_ERROR_CODE = 'network';

export const STREAM_INTERRUPTED_CODE = 'stream_interrupted';

const MESSAGES_URL = `${import.meta.env.VITE_API_URL}/api/v1/assistant/messages`;

export async function fetchAssistantStatus() {
  const response = await httpClient.get('/api/v1/assistant/status');

  return response.data.data;
}

function readXsrfToken() {
  const cookie = document.cookie
    .split(';')
    .map((part) => part.trim())
    .find((part) => part.startsWith('XSRF-TOKEN='));

  return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : null;
}

async function refreshCsrfCookie(signal) {
  try {
    await ensureCsrfCookie();
  } catch (error) {
    if (signal.aborted) {
      throw error;
    }

    throw createClientError(NETWORK_ERROR_CODE, UNAVAILABLE_MESSAGE);
  }
}

async function readErrorBody(response) {
  try {
    return await response.json();
  } catch {
    return null;
  }
}

async function postMessages(messages, signal, csrfRetried = false) {
  if (readXsrfToken() === null) {
    await refreshCsrfCookie(signal);
  }

  const token = readXsrfToken();
  let response;

  try {
    response = await fetch(MESSAGES_URL, {
      method: 'POST',
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(token === null ? {} : { 'X-XSRF-TOKEN': token }),
      },
      body: JSON.stringify({ messages }),
      signal,
    });
  } catch (error) {
    if (signal.aborted) {
      throw error;
    }

    throw createClientError(NETWORK_ERROR_CODE, UNAVAILABLE_MESSAGE);
  }

  if (response.status === 419 && !csrfRetried) {
    await refreshCsrfCookie(signal);

    return postMessages(messages, signal, true);
  }

  if (!response.ok) {
    const data = await readErrorBody(response);

    throw normalizeApiError({
      response: { status: response.status, data, headers: response.headers },
    });
  }

  return response;
}

export async function* streamAssistantAnswer({ messages, signal }) {
  const response = await postMessages(messages, signal);

  try {
    yield* readNdjson(response.body, signal);
  } catch (error) {
    if (signal.aborted) {
      throw error;
    }

    throw createClientError(STREAM_INTERRUPTED_CODE, INTERRUPTED_ANSWER_MESSAGE);
  }
}
