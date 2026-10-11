import { describe, expect, it } from 'vitest';
import { assistantReducer, initialAssistantState } from '@/features/assistant/assistantReducer';

const QUESTION = { id: 'question-1', role: 'user', content: 'Cuffie?' };

function started(requestId = 'answer-1') {
  return assistantReducer(initialAssistantState, { type: 'start', requestId, question: QUESTION });
}

describe('assistantReducer', () => {
  it('waits after the question and streams the fragments into one answer', () => {
    let state = started();

    expect(state).toMatchObject({ status: 'waiting', requestId: 'answer-1', messages: [QUESTION] });

    state = assistantReducer(state, { type: 'text', requestId: 'answer-1', delta: 'Ti ' });
    state = assistantReducer(state, { type: 'text', requestId: 'answer-1', delta: 'consiglio' });

    expect(state.status).toBe('streaming');
    expect(state.messages[1]).toMatchObject({ role: 'assistant', content: 'Ti consiglio' });

    state = assistantReducer(state, { type: 'done', requestId: 'answer-1' });

    expect(state).toMatchObject({ status: 'idle', requestId: null });
  });

  it('ignores the events of another request', () => {
    const state = started();

    expect(assistantReducer(state, { type: 'text', requestId: 'answer-0', delta: 'vecchio' })).toBe(
      state,
    );
  });

  it('marks a stopped answer as interrupted and drops its products', () => {
    let state = started();
    state = assistantReducer(state, { type: 'text', requestId: 'answer-1', delta: 'Parziale' });
    state = assistantReducer(state, {
      type: 'products',
      requestId: 'answer-1',
      products: [{ id: 1 }],
    });
    state = assistantReducer(state, { type: 'stop', requestId: 'answer-1' });

    expect(state.status).toBe('idle');
    expect(state.messages[1]).toMatchObject({
      interrupted: true,
      products: [],
      content: 'Parziale',
    });
  });

  it('adds an empty interrupted answer when nothing had arrived', () => {
    const state = assistantReducer(started(), { type: 'stop', requestId: 'answer-1' });

    expect(state.messages[1]).toMatchObject({ role: 'assistant', content: '', interrupted: true });
  });

  it('keeps the failed answer until the retry replaces it', () => {
    let state = started();
    state = assistantReducer(state, { type: 'text', requestId: 'answer-1', delta: 'A metà' });
    state = assistantReducer(state, {
      type: 'fail',
      requestId: 'answer-1',
      error: { kind: 'interrupted', message: 'La risposta si è interrotta prima della fine.' },
    });

    expect(state.status).toBe('error');
    expect(state.messages[1]).toMatchObject({ failed: true, content: 'A metà' });

    state = assistantReducer(state, { type: 'start', requestId: 'answer-2', question: null });

    expect(state).toMatchObject({ status: 'waiting', error: null, messages: [QUESTION] });
  });

  it('empties everything on reset', () => {
    expect(assistantReducer(started(), { type: 'reset' })).toEqual(initialAssistantState);
  });
});
