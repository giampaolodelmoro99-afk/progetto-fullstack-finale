<?php

namespace App\Actions\Security;

use App\Contracts\Security\PasskeyCeremony;
use App\Enums\PasskeyPurpose;
use App\Enums\SecurityEventType;
use App\Models\Passkey;
use App\Models\User;
use App\Notifications\PasskeyAddedNotification;
use App\Services\Security\PasskeyChallengeStore;
use App\Support\UserAgentLabel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves the passkey created by the browser with the registration options
 * of the session, which are single use. The passkey and its passkey_added
 * event are written together; the notice leaves after the commit. No
 * recovery codes: they exist only with the TOTP.
 */
final class RegisterPasskeyAction
{
    public function __construct(
        private readonly Request $request,
        private readonly PasskeyCeremony $ceremony,
        private readonly PasskeyChallengeStore $challenges,
        private readonly RecordSecurityEventAction $recordSecurityEvent,
    ) {}

    /**
     * @param  array<string, mixed>  $credential
     */
    public function execute(User $user, string $name, array $credential): Passkey
    {
        $options = $this->challenges->pull(PasskeyPurpose::Registration)
            ?? throw ValidationException::withMessages([
                'credential' => 'La registrazione della passkey è scaduta: riprova.',
            ]);

        $passkey = DB::transaction(function () use ($user, $name, $credential, $options): ?Passkey {
            $passkey = $this->ceremony->register($user, $name, $credential, $options);

            if ($passkey !== null) {
                $this->recordSecurityEvent->execute($user, SecurityEventType::PasskeyAdded, ['passkey_name' => $passkey->name]);
            }

            return $passkey;
        });

        if ($passkey === null) {
            throw ValidationException::withMessages([
                'credential' => 'Non è stato possibile registrare la passkey: riprova.',
            ]);
        }

        $user->notify((new PasskeyAddedNotification(
            $passkey->name,
            now(),
            UserAgentLabel::for($this->request->userAgent()),
        ))->afterCommit());

        return $passkey;
    }
}
