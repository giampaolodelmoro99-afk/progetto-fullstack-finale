import InboxOutlined from '@mui/icons-material/InboxOutlined';
import Button from '@mui/material/Button';
import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { EmptyState } from '@/components/feedback/EmptyState';
import { ErrorState } from '@/components/feedback/ErrorState';
import { PageLoader } from '@/components/feedback/PageLoader';
import { renderWithProviders } from '@/test/renderWithProviders';

describe('ErrorState', () => {
  it('shows the message and retries on demand', async () => {
    const user = userEvent.setup();
    const onRetry = vi.fn();
    renderWithProviders(
      <ErrorState
        title="Impossibile caricare gli ordini"
        message="Controlla la connessione e riprova."
        onRetry={onRetry}
      />,
      { user: null },
    );

    const alert = screen.getByRole('alert');
    expect(alert).toHaveTextContent('Impossibile caricare gli ordini');
    expect(alert).toHaveTextContent('Controlla la connessione e riprova.');

    await user.click(screen.getByRole('button', { name: 'Riprova' }));

    expect(onRetry).toHaveBeenCalledOnce();
  });

  it('hides the retry button without a handler', () => {
    renderWithProviders(<ErrorState message="Errore." />, { user: null });

    expect(screen.queryByRole('button', { name: 'Riprova' })).not.toBeInTheDocument();
  });
});

describe('EmptyState', () => {
  it('shows title, description and action', () => {
    renderWithProviders(
      <EmptyState
        icon={<InboxOutlined />}
        title="Non hai ancora fatto ordini"
        description="Quando acquisterai qualcosa, qui troverai lo stato e i dettagli di ogni ordine."
        action={<Button>Scopri la collezione</Button>}
      />,
      { user: null },
    );

    expect(
      screen.getByRole('heading', { level: 2, name: 'Non hai ancora fatto ordini' }),
    ).toBeInTheDocument();
    expect(
      screen.getByText(
        'Quando acquisterai qualcosa, qui troverai lo stato e i dettagli di ogni ordine.',
      ),
    ).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Scopri la collezione' })).toBeInTheDocument();
  });

  it('accepts another heading level', () => {
    renderWithProviders(<EmptyState title="Nessun prodotto trovato" titleComponent="h3" />, {
      user: null,
    });

    expect(
      screen.getByRole('heading', { level: 3, name: 'Nessun prodotto trovato' }),
    ).toBeInTheDocument();
  });
});

describe('PageLoader', () => {
  it('announces the loading', () => {
    renderWithProviders(<PageLoader />, { user: null });

    expect(screen.getByRole('progressbar', { name: 'Caricamento in corso' })).toBeInTheDocument();
  });
});
