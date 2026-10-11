import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { HttpResponse } from 'msw';
import { describe, expect, it } from 'vitest';
import { domainError } from '@/test/authHelpers';
import { buildAdmin } from '@/test/factories';
import {
  cancelIdentityConfirmation,
  confirmIdentityWith,
  protectedEndpoint,
  renderSecurityPage,
} from '@/test/security';

const OPEN_ORDERS_MESSAGE =
  "Hai ordini in corso: potrai eliminare l'account dopo la consegna, oppure annullandoli.";

async function openDeleteDialog(user) {
  await user.click(await screen.findByRole('button', { name: "Elimina l'account" }));

  return screen.getByRole('dialog', { name: 'Vuoi eliminare il tuo account?' });
}

async function confirmDeletion(user, dialog) {
  await user.click(
    within(dialog).getByRole('checkbox', {
      name: "Ho capito che l'operazione non si può annullare",
    }),
  );
  await user.click(within(dialog).getByRole('button', { name: "Elimina l'account" }));
}

describe('DeleteAccountSection', () => {
  it('is not shown to an admin', async () => {
    renderSecurityPage(buildAdmin());

    expect(await screen.findByText('Ultima modifica: 12 settembre 2026')).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: 'Elimina account' })).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: "Elimina l'account" })).not.toBeInTheDocument();
  });

  it('explains what is deleted and what is kept', async () => {
    const user = userEvent.setup();

    renderSecurityPage();

    expect(
      await screen.findByText(
        "Cancelli i tuoi dati, gli indirizzi e l'accesso. L'operazione non si può annullare.",
      ),
    ).toBeInTheDocument();
    const dialog = await openDeleteDialog(user);

    expect(
      within(dialog).getByRole('heading', { name: 'Cosa viene cancellato' }),
    ).toBeInTheDocument();
    expect(within(dialog).getByText('I tuoi dati personali')).toBeInTheDocument();
    expect(within(dialog).getByText('Gli indirizzi salvati')).toBeInTheDocument();
    expect(within(dialog).getByText('Il carrello')).toBeInTheDocument();
    expect(
      within(dialog).getByText("L'accesso: password, passkey e doppia autenticazione"),
    ).toBeInTheDocument();
    expect(within(dialog).getByRole('heading', { name: 'Cosa conserviamo' })).toBeInTheDocument();
    expect(within(dialog).getByText('Gli ordini, per 10 anni')).toBeInTheDocument();
    expect(
      within(dialog).getByText('È un obbligo di legge: i documenti di vendita vanno conservati.'),
    ).toBeInTheDocument();
  });

  it('enables the deletion only after the checkbox and resets it when reopened', async () => {
    const user = userEvent.setup();

    renderSecurityPage();

    let dialog = await openDeleteDialog(user);
    const confirm = within(dialog).getByRole('button', { name: "Elimina l'account" });
    expect(confirm).toBeDisabled();
    await user.click(within(dialog).getByRole('checkbox'));
    expect(confirm).toBeEnabled();
    await user.click(within(dialog).getByRole('button', { name: 'Annulla' }));
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());

    dialog = await openDeleteDialog(user);
    expect(within(dialog).getByRole('checkbox')).not.toBeChecked();
    expect(within(dialog).getByRole('button', { name: "Elimina l'account" })).toBeDisabled();
  });

  it('deletes the account after the identity confirmation and goes home', async () => {
    const user = userEvent.setup();
    const calls = protectedEndpoint(
      'delete',
      '/api/v1/me',
      () => new HttpResponse(null, { status: 204 }),
    );

    const { router, queryClient } = renderSecurityPage();

    const dialog = await openDeleteDialog(user);
    await confirmDeletion(user, dialog);
    await confirmIdentityWith(user, "Prima di eliminare l'account, conferma che sei tu.");

    expect(await screen.findByText('Il tuo account è stato eliminato.')).toBeInTheDocument();
    await waitFor(() => expect(router.state.location.pathname).toBe('/'));
    expect(queryClient.getQueryData(['me'])).toBeNull();
    expect(queryClient.getQueryData(['security', 'overview'])).toBeUndefined();
    expect(calls).toHaveLength(2);
  });

  it('keeps the dialog open without errors when the identity confirmation is cancelled', async () => {
    const user = userEvent.setup();
    protectedEndpoint('delete', '/api/v1/me', () => new HttpResponse(null, { status: 204 }));

    const { router } = renderSecurityPage();

    const dialog = await openDeleteDialog(user);
    await confirmDeletion(user, dialog);
    await cancelIdentityConfirmation(user);

    await waitFor(() =>
      expect(
        screen.queryByRole('dialog', { name: 'Conferma la tua identità' }),
      ).not.toBeInTheDocument(),
    );
    expect(
      screen.getByRole('dialog', { name: 'Vuoi eliminare il tuo account?' }),
    ).toBeInTheDocument();
    expect(within(dialog).getAllByRole('alert')).toHaveLength(1);
    expect(within(dialog).queryByText("Conferma dell'identità annullata.")).not.toBeInTheDocument();
    expect(router.state.location.pathname).toBe('/account/security');
  });

  it('explains the open orders and links to them', async () => {
    const user = userEvent.setup();
    protectedEndpoint('delete', '/api/v1/me', () =>
      domainError(409, 'account_has_open_orders', OPEN_ORDERS_MESSAGE),
    );

    const { router } = renderSecurityPage();

    const dialog = await openDeleteDialog(user);
    await confirmDeletion(user, dialog);
    await confirmIdentityWith(user, "Prima di eliminare l'account, conferma che sei tu.");

    const blocked = await screen.findByRole('dialog', {
      name: "Per ora non puoi eliminare l'account",
    });
    expect(within(blocked).getByText(OPEN_ORDERS_MESSAGE)).toBeInTheDocument();
    await user.click(within(blocked).getByRole('link', { name: 'I miei ordini' }));

    await waitFor(() => expect(router.state.location.pathname).toBe('/account/orders'));
  });

  it('goes back to the confirmation content after closing the open orders dialog', async () => {
    const user = userEvent.setup();
    protectedEndpoint('delete', '/api/v1/me', () =>
      domainError(409, 'account_has_open_orders', OPEN_ORDERS_MESSAGE),
    );

    renderSecurityPage();

    const dialog = await openDeleteDialog(user);
    await confirmDeletion(user, dialog);
    await confirmIdentityWith(user, "Prima di eliminare l'account, conferma che sei tu.");
    const blocked = await screen.findByRole('dialog', {
      name: "Per ora non puoi eliminare l'account",
    });
    await user.click(within(blocked).getByRole('button', { name: 'Chiudi' }));
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());

    const reopened = await openDeleteDialog(user);
    expect(within(reopened).getByRole('checkbox')).not.toBeChecked();
  });

  it('shows other errors inside the dialog', async () => {
    const user = userEvent.setup();
    protectedEndpoint('delete', '/api/v1/me', () =>
      HttpResponse.json({ message: 'Errore' }, { status: 500 }),
    );

    renderSecurityPage();

    const dialog = await openDeleteDialog(user);
    await confirmDeletion(user, dialog);
    await confirmIdentityWith(user, "Prima di eliminare l'account, conferma che sei tu.");

    expect(
      await within(dialog).findByText('Si è verificato un errore imprevisto. Riprova più tardi.'),
    ).toBeInTheDocument();
  });
});
