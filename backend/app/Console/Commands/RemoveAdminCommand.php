<?php

namespace App\Console\Commands;

use App\Actions\Admin\RemoveAdminAction;
use App\Enums\UserRole;
use App\Exceptions\Admin\AdminNotFoundException;
use App\Exceptions\Admin\LastAdminException;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Removes an admin, the twin of admin:create. The account found is shown
 * and its email must be typed again to confirm; a customer is never touched.
 * The last active admin is refused here, to avoid a useless confirmation,
 * and again by the action under the lock, which is the check that counts.
 */
final class RemoveAdminCommand extends Command
{
    protected $signature = 'admin:remove';

    protected $description = 'Remove an admin account by anonymizing it, asking the email twice';

    public function handle(RemoveAdminAction $removeAdmin): int
    {
        $email = $this->normalize($this->ask('Email'));

        $user = User::query()->where('email', $email)->whereNull('anonymized_at')->first();

        if ($user === null) {
            return $this->refuse('Nessun account con questa email.');
        }

        if (! $user->isAdmin()) {
            return $this->refuse('Questo account è un cliente: il comando rimuove solo amministratori.');
        }

        if ($this->activeAdmins() === 1) {
            return $this->refuse(LastAdminException::MESSAGE);
        }

        $this->line("Amministratore trovato: {$user->name} <{$user->email}>");

        if ($this->normalize($this->ask('Riscrivi l\'email per confermare la rimozione')) !== $user->email) {
            return $this->refuse('Conferma non corrispondente: nessuna modifica.');
        }

        try {
            $removeAdmin->execute($user);
        } catch (AdminNotFoundException|LastAdminException $exception) {
            return $this->refuse($exception->getMessage());
        }

        $this->components->info("Amministratore rimosso: {$email}");

        return self::SUCCESS;
    }

    private function normalize(?string $email): string
    {
        return Str::lower(trim((string) $email));
    }

    private function activeAdmins(): int
    {
        return User::query()->where('role', UserRole::Admin)->whereNull('anonymized_at')->count();
    }

    private function refuse(string $message): int
    {
        $this->components->error("Amministratore non rimosso. {$message}");

        return self::FAILURE;
    }
}
