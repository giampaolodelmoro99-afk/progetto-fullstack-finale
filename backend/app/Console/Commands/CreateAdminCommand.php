<?php

namespace App\Console\Commands;

use App\Actions\Admin\CreateAdminAction;
use App\Support\Auth\RegistrationRules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Creates an admin with the rules of the registration. The password is
 * always asked hidden and never accepted as an option, so it stays out of
 * the shell history; it is never printed or logged. Without a terminal that
 * can hide the input the command fails instead of asking in clear.
 */
final class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create';

    protected $description = 'Create an admin account, asking name, email and password';

    public function handle(CreateAdminAction $createAdmin): int
    {
        $data = RegistrationRules::normalize([
            'name' => $this->ask('Nome e cognome'),
            'email' => $this->ask('Email'),
            'password' => $this->secret('Password (almeno 10 caratteri, con maiuscole, minuscole e numeri)', false),
            'password_confirmation' => $this->secret('Conferma la password', false),
        ]);

        $validator = Validator::make($data, RegistrationRules::rules(), RegistrationRules::messages());

        try {
            $admin = $createAdmin->execute($validator->validate());
        } catch (ValidationException $exception) {
            $this->components->error('Amministratore non creato.');

            foreach ($exception->validator->errors()->all() as $message) {
                $this->line("  {$message}");
            }

            return self::FAILURE;
        }

        $this->components->info("Amministratore creato: {$admin->email}");

        return self::SUCCESS;
    }
}
