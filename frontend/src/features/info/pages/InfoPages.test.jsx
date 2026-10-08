import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { renderApp } from '@/test/authHelpers';
import { apiUrl } from '@/test/mocks/handlers';
import { server } from '@/test/mocks/server';

const SECTIONS = [
  {
    id: 'spedizioni',
    title: 'Consegne veloci',
    paragraphs: ['Arriviamo in 48 ore.', 'Costa 5,00 €.'],
  },
  { id: 'resi', title: 'Restituzioni', paragraphs: ['Hai un mese di tempo.'] },
  { id: 'garanzia', title: 'Copertura', paragraphs: ['Due anni.'] },
  { id: 'contatti', title: 'Scrivici', paragraphs: ['Rispondiamo in giornata.'] },
];

function serveSupport(respond = () => HttpResponse.json({ data: SECTIONS })) {
  server.use(http.get(apiUrl('/api/v1/shop/support'), respond));
}

function sectionHeadings() {
  return within(screen.getByRole('main'))
    .getAllByRole('heading', { level: 2 })
    .map((heading) => heading.textContent);
}

afterEach(() => {
  vi.restoreAllMocks();
});

describe('SupportPage', () => {
  it('shows the sections received from the api with their anchors', async () => {
    serveSupport();

    renderApp('/assistenza');

    expect(screen.getByRole('heading', { level: 1, name: 'Assistenza' })).toBeInTheDocument();
    expect(await screen.findByRole('region', { name: 'Consegne veloci' })).toHaveAttribute(
      'id',
      'spedizioni',
    );
    expect(sectionHeadings()).toEqual(['Consegne veloci', 'Restituzioni', 'Copertura', 'Scrivici']);
    expect(screen.getByRole('region', { name: 'Scrivici' })).toHaveAttribute('id', 'contatti');
    expect(screen.getByText('Costa 5,00 €.')).toBeInTheDocument();
    expect(screen.getByText('Hai un mese di tempo.')).toBeInTheDocument();
  });

  it('scrolls to the section of the hash once the content is loaded', async () => {
    const scrolled = [];
    vi.spyOn(Element.prototype, 'scrollIntoView').mockImplementation(function scroll() {
      scrolled.push(this.id);
    });
    serveSupport();

    renderApp('/assistenza#contatti');

    await screen.findByRole('region', { name: 'Scrivici' });
    await waitFor(() => expect(scrolled).toEqual(['contatti']));
  });

  it('scrolls again when only the hash changes, from the footer links', async () => {
    const user = userEvent.setup();
    const scrolled = [];
    vi.spyOn(Element.prototype, 'scrollIntoView').mockImplementation(function scroll() {
      scrolled.push(this.id);
    });
    serveSupport();

    renderApp('/assistenza');

    await screen.findByRole('region', { name: 'Scrivici' });
    const footer = within(screen.getByRole('contentinfo'));
    await user.click(footer.getByRole('link', { name: 'Resi e rimborsi' }));
    await user.click(footer.getByRole('link', { name: 'Garanzia' }));

    await waitFor(() => expect(scrolled).toEqual(['resi', 'garanzia']));
  });

  it('ignores a malformed hash', async () => {
    serveSupport();

    renderApp('/assistenza#%E0');

    expect(await screen.findByRole('region', { name: 'Scrivici' })).toBeInTheDocument();
  });

  it('shows a skeleton while loading', async () => {
    serveSupport(async () => {
      await new Promise((resolve) => setTimeout(resolve, 50));
      return HttpResponse.json({ data: SECTIONS });
    });

    renderApp('/assistenza');

    expect(screen.getByLabelText("Caricamento dell'assistenza")).toHaveAttribute(
      'aria-busy',
      'true',
    );
    expect(await screen.findByRole('region', { name: 'Consegne veloci' })).toBeInTheDocument();
  });

  it('retries after an error', async () => {
    const user = userEvent.setup();
    let calls = 0;
    serveSupport(() => {
      calls += 1;
      return calls === 1
        ? HttpResponse.json({ message: 'Errore.' }, { status: 500 })
        : HttpResponse.json({ data: SECTIONS });
    });

    renderApp('/assistenza');

    expect(
      await screen.findByText("Non è stato possibile caricare l'assistenza"),
    ).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Riprova' }));

    expect(await screen.findByRole('region', { name: 'Consegne veloci' })).toBeInTheDocument();
  });

  it('explains when there is nothing to show', async () => {
    serveSupport(() => HttpResponse.json({ data: [] }));

    renderApp('/assistenza');

    expect(
      await screen.findByRole('heading', { name: 'Nessuna informazione disponibile' }),
    ).toBeInTheDocument();
  });
});

describe('LegalPage', () => {
  it('shows the demo notice and the four sections with their anchors', () => {
    renderApp('/note-legali');

    expect(screen.getByRole('heading', { level: 1, name: 'Note legali' })).toBeInTheDocument();
    expect(
      screen.getByText(
        'Testi dimostrativi per un progetto didattico: non costituiscono condizioni contrattuali reali.',
      ),
    ).toBeInTheDocument();
    expect(sectionHeadings()).toEqual([
      'Condizioni di vendita',
      'Diritto di recesso',
      'Privacy',
      'Cookie',
    ]);
    expect(screen.getByRole('region', { name: 'Condizioni di vendita' })).toHaveAttribute(
      'id',
      'condizioni-di-vendita',
    );
    expect(screen.getByRole('region', { name: 'Diritto di recesso' })).toHaveAttribute(
      'id',
      'diritto-di-recesso',
    );
    expect(screen.getByRole('region', { name: 'Privacy' })).toHaveAttribute('id', 'privacy');
    expect(screen.getByRole('region', { name: 'Cookie' })).toHaveAttribute('id', 'cookie');
  });

  it('describes the behaviour of the shop', () => {
    renderApp('/note-legali');

    expect(
      within(screen.getByRole('region', { name: 'Diritto di recesso' })).getByText(/30 giorni/),
    ).toHaveTextContent('14 giorni');
    const privacy = within(screen.getByRole('region', { name: 'Privacy' }));
    expect(privacy.getByText(/10 anni/)).toBeInTheDocument();
    expect(privacy.getByText(/ultime 4 cifre/)).toBeInTheDocument();
    expect(privacy.getByText(/non conserva le conversazioni/)).toBeInTheDocument();
    expect(
      within(screen.getByRole('region', { name: 'Cookie' })).getByText(/solo cookie tecnici/),
    ).toBeInTheDocument();
  });

  it('scrolls to the section of the hash', async () => {
    const scrolled = [];
    vi.spyOn(Element.prototype, 'scrollIntoView').mockImplementation(function scroll() {
      scrolled.push(this.id);
    });

    renderApp('/note-legali#cookie');

    await waitFor(() => expect(scrolled).toEqual(['cookie']));
  });
});
