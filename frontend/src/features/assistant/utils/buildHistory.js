import {
  MAX_ASSISTANT_MESSAGE_LENGTH,
  MAX_HISTORY_MESSAGES,
} from '@/features/assistant/assistantConstants';

function isComplete(message) {
  return message.role === 'user' || (!message.interrupted && !message.failed);
}

export function buildHistory(messages) {
  return messages
    .filter(isComplete)
    .slice(-MAX_HISTORY_MESSAGES)
    .map((message) => ({
      role: message.role,
      content:
        message.role === 'assistant'
          ? message.content.slice(0, MAX_ASSISTANT_MESSAGE_LENGTH)
          : message.content,
    }));
}
