<?php

namespace App\Actions\Security;

use App\Enums\SecurityEventType;
use App\Models\Passkey;
use App\Models\User;
use App\Notifications\PasskeyRemovedNotification;
use App\Support\UserAgentLabel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Removes a passkey of the user, behind the identity confirmation. The
 * passkey and its passkey_removed event, with the name it had, change
 * together; the notice leaves after the commit.
 */
final class DeletePasskeyAction
{
    public function __construct(
        private readonly Request $request,
        private readonly RecordSecurityEventAction $recordSecurityEvent,
    ) {}

    public function execute(User $user, Passkey $passkey): void
    {
        $name = $passkey->name;

        DB::transaction(function () use ($user, $passkey, $name): void {
            $passkey->delete();

            $this->recordSecurityEvent->execute($user, SecurityEventType::PasskeyRemoved, ['passkey_name' => $name]);
        });

        $user->notify((new PasskeyRemovedNotification(
            $name,
            now(),
            UserAgentLabel::for($this->request->userAgent()),
        ))->afterCommit());
    }
}
