import {
  INTERRUPTED_ANSWER_MESSAGE,
  UNEXPECTED_ERROR_MESSAGE,
  tooManyQuestionsMessage,
} from '@/features/assistant/assistantConstants';
import { isNormalizedApiError } from '@/utils/normalizeApiError';

export function streamError(message) {
  return { kind: 'interrupted', message, retryAfter: null };
}

export function toAssistantError(error) {
  if (!isNormalizedApiError(error)) {
    return streamError(INTERRUPTED_ANSWER_MESSAGE);
  }

  if (error.status === 429) {
    return {
      kind: 'rate_limited',
      message: tooManyQuestionsMessage(error.retryAfter),
      retryAfter: error.retryAfter,
    };
  }

  if (error.status === 422) {
    return { kind: 'invalid', message: UNEXPECTED_ERROR_MESSAGE, retryAfter: null };
  }

  if (error.status === 503 && error.code === 'assistant_busy') {
    return { kind: 'busy', message: error.message, retryAfter: null };
  }

  return { kind: 'failed', message: error.message, retryAfter: null };
}
