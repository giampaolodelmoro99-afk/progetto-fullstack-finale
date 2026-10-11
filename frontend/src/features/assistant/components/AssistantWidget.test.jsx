import { act, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { queryKeys } from '@/api/queryKeys';
import {
  assistantStatus,
  clearXsrfCookie,
  completeAnswer,
  ndjsonResponse,
  openAnswer,
  recordAssistantMessages,
  setXsrfCookie,
  textEvents,
} from '@/test/assistant';
import { domainError, renderApp } from '@/test/authHelpers';
import { buildAdmin, buildCart, buildProduct, buildUser } from '@/test/factories';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';
import { MOBILE_WIDTH, setViewportWidth } from '@/test/viewport';

const REFUSAL = 'Posso aiutarti solo con i prodotti e i servizi di Casa del Silicio.';

function launcher() {
  return screen.getByRole('button', { name: "Chiedi all'assistente" });
}

function panel() {
  return screen.getByRole('dialog', { name: 'Assistente di Casa del Silicio' });
}

function questionField() {
  return within(panel()).getByLabelText('La tua domanda');
}

function conversation() {
  return within(panel()).getByRole('group', { name: 'Conversazione' });
}

function liveRegion() {
  return within(panel()).getByRole('status');
}

async function openAssistant(user) {
  await user.click(await screen.findByRole('button', { name: "Chiedi all'assistente" }));

  return panel();
}

async function ask(user, text) {
  await user.type(questionField(), text);
  await user.click(within(panel()).getByRole('button', { name: 'Invia' }));
}

function countStatusRequests() {
  const counter = { count: 0 };
  server.use(
    http.get(apiUrl('/api/v1/assistant/status'), () => {
      counter.count += 1;
      return HttpResponse.json({ data: { available: true } });
    }),
  );

  return counter;
}

beforeEach(() => {
  setXsrfCookie('token');
});

afterEach(() => {
  clearXsrfCookie();
  window.localStorage.clear();
  window.sessionStorage.clear();
});

describe('AssistantWidget launcher', () => {
  it('opens the panel, focuses the question and returns the focus when it closes', async () => {
    const user = userEvent.setup();
    renderApp('/');

    expect(await screen.findByRole('button', { name: "Chiedi all'assistente" })).toHaveAttribute(
      'aria-expanded',
      'false',
    );

    await user.click(launcher());

    expect(questionField()).toHaveFocus();
    const toggle = screen
      .getAllByRole('button', { name: "Chiudi l'assistente" })
      .find((button) => button.getAttribute('aria-expanded') === 'true');
    expect(toggle).toBeDefined();

    await user.click(within(panel()).getByRole('button', { name: "Chiudi l'assistente" }));

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(launcher()).toHaveFocus();
    expect(launcher()).toHaveAttribute('aria-expanded', 'false');
  });

  it('closes the panel with Escape', async () => {
    const user = userEvent.setup();
    renderApp('/');

    await openAssistant(user);
    await user.keyboard('{Escape}');

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
  });

  it('is disabled with a tooltip when the assistant is not available', async () => {
    assistantStatus(false);
    renderApp('/');

    expect(await screen.findByText('Assistente non disponibile')).toBeInTheDocument();
    expect(launcher()).toBeDisabled();
  });

  it('is disabled with a tooltip when the status request fails', async () => {
    server.use(
      http.get(apiUrl('/api/v1/assistant/status'), () =>
        HttpResponse.json({ message: 'Errore.' }, { status: 500 }),
      ),
    );
    renderApp('/');

    expect(await screen.findByText('Assistente non disponibile')).toBeInTheDocument();
    expect(launcher()).toBeDisabled();
  });

  it('never appears to an admin and never asks for the status', async () => {
    const status = countStatusRequests();
    renderApp('/', { user: buildAdmin() });

    await screen.findByRole('button', { name: 'Il tuo account' });
    expect(screen.queryByRole('button', { name: "Chiedi all'assistente" })).not.toBeInTheDocument();
    expect(status.count).toBe(0);
  });

  it('is hidden on mobile in the cart and in the checkout, and shown elsewhere', async () => {
    setViewportWidth(MOBILE_WIDTH);
    const { router } = renderApp('/', { user: buildUser() });

    expect(
      await screen.findByRole('button', { name: "Chiedi all'assistente" }),
    ).toBeInTheDocument();

    await act(() => router.navigate('/cart'));
    expect(screen.queryByRole('button', { name: "Chiedi all'assistente" })).not.toBeInTheDocument();

    await act(() => router.navigate('/checkout'));
    expect(screen.queryByRole('button', { name: "Chiedi all'assistente" })).not.toBeInTheDocument();
  });

  it('stays visible on desktop in the cart', async () => {
    renderApp('/cart', { user: buildUser() });

    expect(
      await screen.findByRole('button', { name: "Chiedi all'assistente" }),
    ).toBeInTheDocument();
  });
});

describe('AssistantWidget status', () => {
  beforeEach(() => {
    vi.useFakeTimers({ shouldAdvanceTime: true });
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('asks again every 30 seconds while the assistant is not available', async () => {
    let requests = 0;
    server.use(
      http.get(apiUrl('/api/v1/assistant/status'), () => {
        requests += 1;
        return HttpResponse.json({ data: { available: requests > 1 } });
      }),
    );
    renderApp('/');

    expect(await screen.findByText('Assistente non disponibile')).toBeInTheDocument();
    expect(launcher()).toBeDisabled();

    await act(async () => vi.advanceTimersByTime(29000));
    expect(requests).toBe(1);

    await act(async () => vi.advanceTimersByTime(1000));

    await waitFor(() => expect(launcher()).toBeEnabled());
    expect(screen.queryByText('Assistente non disponibile')).not.toBeInTheDocument();

    await act(async () => vi.advanceTimersByTime(60000));
    expect(requests).toBe(2);
  });
});

describe('AssistantWidget empty conversation', () => {
  it('shows the welcome, the four suggestions and the note', async () => {
    const user = userEvent.setup();
    renderApp('/');

    const dialog = await openAssistant(user);

    expect(within(dialog).getByText('Ciao, come posso aiutarti?')).toBeInTheDocument();
    expect(
      within(dialog).getByText('Chiedimi dei prodotti, della spedizione o dei resi.'),
    ).toBeInTheDocument();
    expect(
      within(within(dialog).getByRole('list', { name: 'Domande suggerite' }))
        .getAllByRole('button')
        .map((button) => button.textContent),
    ).toEqual([
      'Cerco cuffie per viaggiare',
      'Un regalo sotto i 100 €',
      'Quanto costa la spedizione?',
      'Come funzionano i resi?',
    ]);
    expect(
      within(dialog).getByText(
        "Le risposte sono generate da un'AI: prezzi e disponibilità sono quelli delle schede.",
      ),
    ).toBeInTheDocument();
    expect(within(dialog).getByRole('button', { name: 'Nuova conversazione' })).toBeDisabled();
  });

  it('sends a suggestion with a click', async () => {
    const user = userEvent.setup();
    const requests = recordAssistantMessages(() =>
      ndjsonResponse(completeAnswer('La spedizione costa 6,90 €.')),
    );
    renderApp('/');

    await openAssistant(user);
    await user.click(screen.getByRole('button', { name: 'Quanto costa la spedizione?' }));

    expect(
      await within(conversation()).findByText('La spedizione costa 6,90 €.'),
    ).toBeInTheDocument();
    expect(requests[0].body).toEqual({
      messages: [{ role: 'user', content: 'Quanto costa la spedizione?' }],
    });
  });

  it('limits the question to 500 characters and counts them', async () => {
    const user = userEvent.setup();
    renderApp('/');

    await openAssistant(user);

    expect(within(panel()).getByText('0/500')).toBeInTheDocument();
    expect(within(panel()).getByRole('button', { name: 'Invia' })).toBeDisabled();

    await user.click(questionField());
    await user.paste('a'.repeat(510));

    expect(questionField()).toHaveValue('a'.repeat(500));
    expect(within(panel()).getByText('500/500')).toBeInTheDocument();
  });
});

describe('AssistantWidget answer', () => {
  it('waits, then streams the text without the markers and shows the cards', async () => {
    const user = userEvent.setup();
    const answer = openAnswer();
    recordAssistantMessages(() => answer.response());
    const product = buildProduct({
      name: 'Cuffie Aria',
      slug: 'cuffie-aria',
      price: 34900,
      availability: { status: 'low_stock', label: 'Ultimi 2 pezzi' },
    });
    renderApp('/');

    await openAssistant(user);
    await ask(user, 'Cerco cuffie');

    expect(
      await within(conversation()).findByText('Sto preparando la risposta…'),
    ).toBeInTheDocument();
    expect(
      within(conversation()).getByText('Di solito servono 15–20 secondi.'),
    ).toBeInTheDocument();
    expect(questionField()).toBeDisabled();
    expect(within(panel()).getByText("L'assistente sta rispondendo.")).toBeInTheDocument();
    expect(within(panel()).getByRole('button', { name: 'Interrompi' })).toBeInTheDocument();
    expect(within(conversation()).getByText('Cerco cuffie')).toBeInTheDocument();

    answer.send(...textEvents('Ti consiglio le Cuffie Aria [[cuf'));

    expect(
      await within(conversation()).findByText('Ti consiglio le Cuffie Aria'),
    ).toBeInTheDocument();
    expect(within(conversation()).queryByText(/\[\[/)).not.toBeInTheDocument();
    expect(
      within(conversation()).queryByText('Sto preparando la risposta…'),
    ).not.toBeInTheDocument();
    expect(within(panel()).getByRole('button', { name: 'Interrompi' })).toBeInTheDocument();
    expect(questionField()).toBeDisabled();
    expect(liveRegion()).toHaveTextContent('');

    answer.send(
      ...textEvents('fie-aria]], con **40 ore** di autonomia.'),
      { type: 'products', data: [product] },
      { type: 'done' },
    );
    answer.close();

    const card = await within(panel()).findByRole('article', { name: 'Cuffie Aria' });
    expect(within(conversation()).getByText('40 ore').tagName).toBe('STRONG');
    expect(within(card).getByText(/349,00/)).toBeInTheDocument();
    expect(within(card).getByText('Ultimi 2 pezzi')).toBeInTheDocument();
    expect(within(card).getByRole('link', { name: 'Vedi prodotto' })).toHaveAttribute(
      'href',
      '/products/cuffie-aria',
    );
    expect(within(panel()).queryByRole('button', { name: 'Interrompi' })).not.toBeInTheDocument();
    expect(questionField()).toBeEnabled();
    expect(questionField()).toHaveFocus();
    expect(liveRegion()).toHaveTextContent('Ti consiglio le Cuffie Aria, con 40 ore di autonomia.');
  });

  it('marks the messages as busy while answering', async () => {
    const user = userEvent.setup();
    const answer = openAnswer();
    recordAssistantMessages(() => answer.response());
    renderApp('/');

    await openAssistant(user);
    await ask(user, 'Ciao');

    const busyArea = await waitFor(() => {
      const area = conversation();
      expect(area).toHaveAttribute('aria-busy', 'true');
      return area;
    });

    answer.send(...completeAnswer('Ciao a te.'));
    answer.close();

    await within(conversation()).findByText('Ciao a te.');
    expect(busyArea).toHaveAttribute('aria-busy', 'false');
  });

  it('moves the focus to Interrompi when the question is sent', async () => {
    const user = userEvent.setup();
    const answer = openAnswer();
    recordAssistantMessages(() => answer.response());
    renderApp('/');

    await openAssistant(user);
    await ask(user, 'Ciao');

    await waitFor(() =>
      expect(within(panel()).getByRole('button', { name: 'Interrompi' })).toHaveFocus(),
    );
    answer.close();
  });

  it('shows the suggestions under a refusal followed by an invitation', async () => {
    const user = userEvent.setup();
    recordAssistantMessages(() =>
      ndjsonResponse(completeAnswer(`  ${REFUSAL} Chiedimi per esempio di un prodotto audio.`)),
    );
    renderApp('/');

    await openAssistant(user);
    await ask(user, 'Una poesia?');

    expect(
      await within(conversation()).findByText(/Chiedimi per esempio di un prodotto audio\./),
    ).toBeInTheDocument();
    expect(within(panel()).getByRole('list', { name: 'Domande suggerite' })).toBeInTheDocument();
  });

  it('hides the suggestions when the refusal is not at the start of the answer', async () => {
    const user = userEvent.setup();
    recordAssistantMessages(() => ndjsonResponse(completeAnswer(`Mi dispiace. ${REFUSAL}`)));
    renderApp('/');

    await openAssistant(user);
    await ask(user, 'Una poesia?');

    expect(await within(conversation()).findByText(/^Mi dispiace\./)).toBeInTheDocument();
    expect(
      within(panel()).queryByRole('list', { name: 'Domande suggerite' }),
    ).not.toBeInTheDocument();
  });

  it('shows the suggestions again under the refusal, and not under another answer', async () => {
    const user = userEvent.setup();
    recordAssistantMessages(({ call }) =>
      ndjsonResponse(completeAnswer(call === 1 ? REFUSAL : 'Le cuffie sono nel catalogo.')),
    );
    renderApp('/');

    await openAssistant(user);
    await ask(user, 'Una ricetta?');

    expect(await within(conversation()).findByText(REFUSAL)).toBeInTheDocument();
    expect(within(panel()).getByRole('list', { name: 'Domande suggerite' })).toBeInTheDocument();

    await ask(user, 'Cuffie?');

    expect(
      await within(conversation()).findByText('Le cuffie sono nel catalogo.'),
    ).toBeInTheDocument();
    expect(
      within(panel()).queryByRole('list', { name: 'Domande suggerite' }),
    ).not.toBeInTheDocument();
  });

  it('sends at most the last 10 messages, with long answers truncated to 4000 characters', async () => {
    const user = userEvent.setup();
    const long = 'a'.repeat(4500);
    const requests = recordAssistantMessages(({ call }) =>
      ndjsonResponse(completeAnswer(call === 1 ? long : `risposta ${call}`)),
    );
    renderApp('/');

    await openAssistant(user);
    for (let index = 1; index <= 6; index += 1) {
      await ask(user, `domanda ${index}`);
      await waitFor(() => expect(questionField()).toBeEnabled());
    }

    expect(requests[1].body.messages[1]).toEqual({ role: 'assistant', content: 'a'.repeat(4000) });
    expect(requests[5].body.messages).toHaveLength(10);
    expect(requests[5].body.messages[0]).toEqual({
      role: 'assistant',
      content: 'a'.repeat(4000),
    });
    expect(requests[5].body.messages[9]).toEqual({ role: 'user', content: 'domanda 6' });
  });
});

describe('AssistantWidget interruption', () => {
  it('keeps the partial text with the interrupted note and leaves it out of the next request', async () => {
    const user = userEvent.setup();
    const answer = openAnswer();
    const requests = recordAssistantMessages(({ call }) =>
      call === 1 ? answer.response() : ndjsonResponse(completeAnswer('Seconda risposta.')),
    );
    const product = buildProduct({ name: 'Radio Onda' });
    renderApp('/');

    await openAssistant(user);
    await ask(user, 'Prima domanda');
    answer.send(...textEvents('Testo parziale'), { type: 'products', data: [product] });
    await within(conversation()).findByText('Testo parziale');

    await user.click(within(panel()).getByRole('button', { name: 'Interrompi' }));

    expect(within(conversation()).getByText('Testo parziale')).toBeInTheDocument();
    expect(within(conversation()).getByText('Risposta interrotta.')).toBeInTheDocument();
    expect(within(panel()).queryByRole('article')).not.toBeInTheDocument();
    expect(within(panel()).queryByRole('note')).not.toBeInTheDocument();
    expect(questionField()).toBeEnabled();
    expect(questionField()).toHaveFocus();
    expect(liveRegion()).toHaveTextContent('Risposta interrotta.');

    await ask(user, 'Seconda domanda');

    expect(await within(conversation()).findByText('Seconda risposta.')).toBeInTheDocument();
    expect(requests[1].body.messages).toEqual([
      { role: 'user', content: 'Prima domanda' },
      { role: 'user', content: 'Seconda domanda' },
    ]);
  });

  it('shows only the interrupted note when no text had arrived', async () => {
    const user = userEvent.setup();
    const answer = openAnswer();
    recordAssistantMessages(() => answer.response());
    renderApp('/');

    await openAssistant(user);
    await ask(user, 'Domanda');
    await within(conversation()).findByText('Sto preparando la risposta…');

    await user.click(within(panel()).getByRole('button', { name: 'Interrompi' }));

    expect(within(conversation()).getByText('Risposta interrotta.')).toBeInTheDocument();
    expect(within(conversation()).queryByText('Assistente')).not.toBeInTheDocument();
    expect(within(panel()).queryByRole('note')).not.toBeInTheDocument();
    expect(
      within(conversation()).queryByText('Sto preparando la risposta…'),
    ).not.toBeInTheDocument();
  });

  it('shows the busy message received after an interruption', async () => {
    const user = userEvent.setup();
    const answer = openAnswer();
    recordAssistantMessages(({ call }) =>
      call === 1
        ? answer.response()
        : domainError(503, 'assistant_busy', "L'assistente è occupato, riprova tra poco."),
    );
    renderApp('/');

    await openAssistant(user);
    await ask(user, 'Prima');
    await within(conversation()).findByText('Sto preparando la risposta…');
    await user.click(within(panel()).getByRole('button', { name: 'Interrompi' }));
    await ask(user, 'Seconda');

    expect(
      await within(conversation()).findByText("L'assistente è occupato, riprova tra poco."),
    ).toBeInTheDocument();
  });

  it('interrupts the answer without errors when the panel closes', async () => {
    const user = userEvent.setup();
    const answer = openAnswer();
    let aborted = false;
    recordAssistantMessages(({ request }) => {
      request.signal.addEventListener('abort', () => {
        aborted = true;
      });
      return answer.response();
    });
    renderApp('/');

    await openAssistant(user);
    await ask(user, 'Domanda');
    answer.send(...textEvents('Inizio'));
    await within(conversation()).findByText('Inizio');

    await user.click(within(panel()).getByRole('button', { name: "Chiudi l'assistente" }));
    await waitFor(() => expect(aborted).toBe(true));
    await openAssistant(user);

    expect(within(conversation()).getByText('Inizio')).toBeInTheDocument();
    expect(within(conversation()).getByText('Risposta interrotta.')).toBeInTheDocument();
    expect(within(panel()).queryByRole('note')).not.toBeInTheDocument();
  });
});

describe('AssistantWidget new conversation', () => {
  it('moves the focus to the question after a complete answer', async () => {
    const user = userEvent.setup();
    recordAssistantMessages(() => ndjsonResponse(completeAnswer('Risposta completa.')));
    renderApp('/');

    await openAssistant(user);
    await ask(user, 'Domanda');
    await within(conversation()).findByText('Risposta completa.');

    await user.click(within(panel()).getByRole('button', { name: 'Nuova conversazione' }));

    expect(within(panel()).getByText('Ciao, come posso aiutarti?')).toBeInTheDocument();
    await waitFor(() => expect(questionField()).toHaveFocus());
  });

  it('interrupts the answer and empties the conversation', async () => {
    const user = userEvent.setup();
    const answer = openAnswer();
    recordAssistantMessages(() => answer.response());
    renderApp('/');

    await openAssistant(user);
    await ask(user, 'Domanda');
    answer.send(...textEvents('Inizio'));
    await within(conversation()).findByText('Inizio');

    await user.click(within(panel()).getByRole('button', { name: 'Nuova conversazione' }));

    expect(within(conversation()).queryByText('Inizio')).not.toBeInTheDocument();
    expect(within(conversation()).queryByText('Domanda')).not.toBeInTheDocument();
    expect(within(conversation()).getByText('Ciao, come posso aiutarti?')).toBeInTheDocument();
    expect(within(panel()).getByRole('button', { name: 'Nuova conversazione' })).toBeDisabled();
    expect(questionField()).toBeEnabled();
  });
});

describe('AssistantWidget errors', () => {
  it('shows the error event under the partial text and replaces it on retry', async () => {
    const user = userEvent.setup();
    const requests = recordAssistantMessages(({ call }) =>
      ndjsonResponse(
        call === 1
          ? [
              ...textEvents('Testo a metà'),
              { type: 'error', message: 'La risposta si è interrotta prima della fine.' },
            ]
          : completeAnswer('Risposta completa.'),
      ),
    );
    renderApp('/');

    await openAssistant(user);
    await ask(user, 'Domanda');

    const alert = await within(panel()).findByRole('note');
    expect(alert).toHaveTextContent('La risposta si è interrotta prima della fine.');
    expect(within(conversation()).getByText('Testo a metà')).toBeInTheDocument();
    expect(liveRegion()).toHaveTextContent('La risposta si è interrotta prima della fine.');
    await waitFor(() =>
      expect(within(alert).getByRole('button', { name: 'Riprova' })).toHaveFocus(),
    );

    await user.click(within(alert).getByRole('button', { name: 'Riprova' }));

    expect(await within(conversation()).findByText('Risposta completa.')).toBeInTheDocument();
    expect(within(conversation()).queryByText('Testo a metà')).not.toBeInTheDocument();
    expect(within(panel()).queryByRole('note')).not.toBeInTheDocument();
    expect(within(conversation()).getAllByText('Domanda')).toHaveLength(1);
    expect(requests[1].body.messages).toEqual([{ role: 'user', content: 'Domanda' }]);
  });

  it('treats a stream closed without done as an interrupted answer', async () => {
    const user = userEvent.setup();
    recordAssistantMessages(() => ndjsonResponse(textEvents('Testo')));
    renderApp('/');

    await openAssistant(user);
    await ask(user, 'Domanda');

    expect(await within(panel()).findByRole('note')).toHaveTextContent(
      'La risposta si è interrotta prima della fine.',
    );
  });

  it.each([
    ['assistant_busy', "L'assistente è occupato, riprova tra poco."],
    ['assistant_unavailable', "L'assistente non è disponibile in questo momento."],
  ])('shows the message of a 503 %s and keeps the conversation', async (code, message) => {
    const user = userEvent.setup();
    recordAssistantMessages(({ call }) =>
      call === 1
        ? ndjsonResponse(completeAnswer('Prima risposta.'))
        : domainError(503, code, message),
    );
    renderApp('/');

    await openAssistant(user);
    await ask(user, 'Prima');
    await within(conversation()).findByText('Prima risposta.');
    await ask(user, 'Seconda');

    const alert = await within(panel()).findByRole('note');
    expect(alert).toHaveTextContent(message);
    expect(within(alert).getByRole('button', { name: 'Riprova' })).toBeEnabled();
    expect(within(conversation()).getByText('Prima risposta.')).toBeInTheDocument();
    expect(within(conversation()).getByText('Seconda')).toBeInTheDocument();
  });

  it('shows the unavailable message on a network error', async () => {
    const user = userEvent.setup();
    recordAssistantMessages(() => HttpResponse.error());
    renderApp('/');

    await openAssistant(user);
    await ask(user, 'Domanda');

    expect(await within(panel()).findByRole('note')).toHaveTextContent(
      "L'assistente non è disponibile in questo momento.",
    );
  });

  it('shows the normalized message with retry on a server error', async () => {
    const user = userEvent.setup();
    recordAssistantMessages(() => HttpResponse.json({ message: 'Server Error' }, { status: 500 }));
    renderApp('/');

    await openAssistant(user);
    await ask(user, 'Domanda');

    const alert = await within(panel()).findByRole('note');
    expect(alert).toHaveTextContent('Si è verificato un errore imprevisto. Riprova più tardi.');
    expect(within(alert).getByRole('button', { name: 'Riprova' })).toBeInTheDocument();
  });

  it('shows the normalized message of another status with retry', async () => {
    const user = userEvent.setup();
    recordAssistantMessages(() => HttpResponse.json({ message: 'Not Found' }, { status: 404 }));
    renderApp('/');

    await openAssistant(user);
    await ask(user, 'Domanda');

    const alert = await within(panel()).findByRole('note');
    expect(alert).toHaveTextContent('La risorsa richiesta non esiste.');
    expect(within(alert).getByRole('button', { name: 'Riprova' })).toBeEnabled();
  });

  it('shows a generic message and a new conversation on a 422, never the server message', async () => {
    const user = userEvent.setup();
    recordAssistantMessages(() =>
      HttpResponse.json(
        {
          message: 'Il campo messages.0.content è obbligatorio.',
          errors: { 'messages.0.content': ['Il campo messages.0.content è obbligatorio.'] },
        },
        { status: 422 },
      ),
    );
    renderApp('/');

    await openAssistant(user);
    await ask(user, 'Domanda');

    const alert = await within(panel()).findByRole('note');
    expect(alert).toHaveTextContent('Si è verificato un errore imprevisto. Riprova più tardi.');
    expect(alert).not.toHaveTextContent('messages.0.content');
    expect(within(alert).queryByRole('button', { name: 'Riprova' })).not.toBeInTheDocument();
    await waitFor(() =>
      expect(within(alert).getByRole('button', { name: 'Nuova conversazione' })).toHaveFocus(),
    );

    await user.click(within(alert).getByRole('button', { name: 'Nuova conversazione' }));

    expect(within(conversation()).getByText('Ciao, come posso aiutarti?')).toBeInTheDocument();
  });

  it('shows the error after a second 419', async () => {
    const user = userEvent.setup();
    recordAssistantMessages(() =>
      HttpResponse.json({ message: 'CSRF token mismatch.' }, { status: 419 }),
    );
    renderApp('/');

    await openAssistant(user);
    await ask(user, 'Domanda');

    expect(await within(panel()).findByRole('note')).toHaveTextContent(
      'La sessione è scaduta. Ricarica la pagina e riprova.',
    );
  });

  it('shows the too many questions message without a block when Retry-After is missing', async () => {
    const user = userEvent.setup();
    recordAssistantMessages(() =>
      HttpResponse.json({ message: 'Too Many Attempts.' }, { status: 429 }),
    );
    renderApp('/');

    await openAssistant(user);
    await ask(user, 'Domanda');

    const alert = await within(panel()).findByRole('note');
    expect(alert).toHaveTextContent('Hai fatto troppe domande, riprova tra poco.');
    expect(within(alert).getByRole('button', { name: 'Riprova' })).toBeEnabled();
    expect(questionField()).toBeEnabled();
  });

  describe('with Retry-After', () => {
    beforeEach(() => {
      vi.useFakeTimers({ shouldAdvanceTime: true });
    });

    afterEach(() => {
      vi.useRealTimers();
    });

    it('blocks retry and the field with a countdown until Retry-After expires', async () => {
      const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
      recordAssistantMessages(({ call }) =>
        call === 1
          ? HttpResponse.json(
              { message: 'Too Many Attempts.' },
              { status: 429, headers: { 'Retry-After': '40' } },
            )
          : ndjsonResponse(completeAnswer('Eccomi.')),
      );
      renderApp('/');

      await openAssistant(user);
      await ask(user, 'Domanda');

      const alert = await within(panel()).findByRole('note');
      expect(alert).toHaveTextContent('Hai fatto troppe domande, riprova tra 40 secondi.');
      expect(within(alert).getByRole('button', { name: 'Riprova' })).toBeDisabled();
      await waitFor(() => expect(alert).toHaveFocus());
      expect(questionField()).toBeDisabled();
      expect(within(panel()).getByText('Potrai scrivere tra 40 secondi.')).toBeInTheDocument();

      await act(async () => vi.advanceTimersByTime(1000));

      expect(within(panel()).getByText('Potrai scrivere tra 39 secondi.')).toBeInTheDocument();
      expect(alert).toHaveTextContent('Hai fatto troppe domande, riprova tra 40 secondi.');

      await act(async () => vi.advanceTimersByTime(39000));

      expect(within(panel()).queryByText(/Potrai scrivere/)).not.toBeInTheDocument();
      expect(questionField()).toBeEnabled();
      expect(questionField()).toHaveFocus();
      expect(within(alert).getByRole('button', { name: 'Riprova' })).toBeEnabled();

      await user.click(within(alert).getByRole('button', { name: 'Riprova' }));

      expect(await within(conversation()).findByText('Eccomi.')).toBeInTheDocument();
    });

    it('keeps the block after a new conversation', async () => {
      const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
      recordAssistantMessages(() =>
        HttpResponse.json(
          { message: 'Too Many Attempts.' },
          { status: 429, headers: { 'Retry-After': '40' } },
        ),
      );
      renderApp('/');

      await openAssistant(user);
      await ask(user, 'Domanda');
      await within(panel()).findByRole('note');

      await user.click(within(panel()).getByRole('button', { name: 'Nuova conversazione' }));

      expect(within(panel()).queryByRole('note')).not.toBeInTheDocument();
      expect(questionField()).toBeDisabled();
      expect(within(panel()).getByText(/Potrai scrivere tra \d+ secondi\./)).toBeInTheDocument();
      expect(
        within(panel()).getByRole('heading', { name: 'Assistente di Casa del Silicio' }),
      ).toHaveFocus();
    });
  });
});

describe('AssistantWidget conversation lifetime', () => {
  it('keeps the conversation while navigating, only in memory', async () => {
    const user = userEvent.setup();
    recordAssistantMessages(() => ndjsonResponse(completeAnswer('Risposta conservata.')));
    const { router } = renderApp('/');

    await openAssistant(user);
    await ask(user, 'Domanda');
    await within(conversation()).findByText('Risposta conservata.');

    await act(() => router.navigate('/products'));

    expect(within(conversation()).getByText('Risposta conservata.')).toBeInTheDocument();
    expect(window.localStorage).toHaveLength(0);
    expect(window.sessionStorage).toHaveLength(0);
  });

  it('empties the conversation when the user logs in and when they log out', async () => {
    const user = userEvent.setup();
    recordAssistantMessages(({ call }) => ndjsonResponse(completeAnswer(`Risposta ${call}.`)));
    const { queryClient } = renderApp('/');

    await openAssistant(user);
    await ask(user, 'Da ospite');
    await within(conversation()).findByText('Risposta 1.');

    await act(() => queryClient.setQueryData(queryKeys.me, buildUser()));

    await waitFor(() =>
      expect(within(conversation()).queryByText('Risposta 1.')).not.toBeInTheDocument(),
    );
    expect(within(conversation()).getByText('Ciao, come posso aiutarti?')).toBeInTheDocument();

    await ask(user, 'Da cliente');
    await within(conversation()).findByText('Risposta 2.');

    await act(() => queryClient.setQueryData(queryKeys.me, null));

    await waitFor(() =>
      expect(within(conversation()).queryByText('Risposta 2.')).not.toBeInTheDocument(),
    );
  });
});

describe('AssistantWidget product cards', () => {
  it('sends a guest to the login from Aggiungi al carrello', async () => {
    const user = userEvent.setup();
    recordAssistantMessages(() =>
      ndjsonResponse(
        completeAnswer('Ti consiglio la Radio Onda [[radio-onda]].', [
          buildProduct({ name: 'Radio Onda', slug: 'radio-onda' }),
        ]),
      ),
    );
    const { router } = renderApp('/products', { user: null });

    await openAssistant(user);
    await ask(user, 'Una radio?');
    const card = await within(panel()).findByRole('article', { name: 'Radio Onda' });

    await user.click(within(card).getByRole('button', { name: 'Aggiungi al carrello' }));

    expect(router.state.location.pathname).toBe('/login');
    expect(router.state.location.state.from.pathname).toBe('/products');
  });

  it('adds the product to the cart of a customer', async () => {
    const user = userEvent.setup();
    const product = buildProduct({ id: 77, name: 'Radio Onda', slug: 'radio-onda' });
    const cartRequests = [];
    server.use(
      http.post(apiUrl('/api/v1/cart/items'), async ({ request }) => {
        cartRequests.push(await request.json());
        return HttpResponse.json({ data: buildCart() });
      }),
    );
    recordAssistantMessages(() =>
      ndjsonResponse(completeAnswer('Ti consiglio la Radio Onda [[radio-onda]].', [product])),
    );
    renderApp('/', { user: buildUser() });

    await openAssistant(user);
    await ask(user, 'Una radio?');
    const card = await within(panel()).findByRole('article', { name: 'Radio Onda' });
    await user.click(within(card).getByRole('button', { name: 'Aggiungi al carrello' }));

    expect(await screen.findByText('Aggiunto al carrello')).toBeInTheDocument();
    expect(cartRequests).toEqual([{ product_id: 77, quantity: 1 }]);
  });

  it('closes the panel on mobile when a product is opened, keeping the conversation', async () => {
    setViewportWidth(MOBILE_WIDTH);
    const user = userEvent.setup();
    recordAssistantMessages(() =>
      ndjsonResponse(
        completeAnswer('Ti consiglio la Radio Onda [[radio-onda]].', [
          buildProduct({ name: 'Radio Onda', slug: 'radio-onda' }),
        ]),
      ),
    );
    const { router } = renderApp('/');

    await openAssistant(user);
    await ask(user, 'Una radio?');
    const card = await within(panel()).findByRole('article', { name: 'Radio Onda' });
    await user.click(within(card).getByRole('link', { name: 'Vedi prodotto' }));

    expect(router.state.location.pathname).toBe('/products/radio-onda');
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());

    await openAssistant(user);

    expect(within(conversation()).getByText('Ti consiglio la Radio Onda.')).toBeInTheDocument();
  });
});
