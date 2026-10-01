<?php

namespace App\Actions\Account;

use App\Actions\Security\RecordSecurityEventAction;
use App\Enums\SecurityEventType;
use App\Models\EmailChangeRequest;
use App\Models\User;
use App\Notifications\EmailChangeConfirmationNotification;
use App\Notifications\EmailChangeRequestedNotification;
use App\Support\UserAgentLabel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Starts an email change: the account keeps its address until the new one
 * is confirmed. A previous pending request is cancelled, so only the last
 * link works. Only the SHA-256 hashes of the two tokens are stored; the
 * plain tokens leave only in the emails, after the commit.
 */
final class RequestEmailChangeAction
{
    public const TOKEN_LENGTH = 64;

    public function __construct(
        private readonly Request $request,
        private readonly RecordSecurityEventAction $recordSecurityEvent,
    ) {}

    public function execute(User $user, string $newEmail): EmailChangeRequest
    {
        $token = Str::random(self::TOKEN_LENGTH);
        $cancelToken = Str::random(self::TOKEN_LENGTH);

        $emailChange = DB::transaction(function () use ($user, $newEmail, $token, $cancelToken): EmailChangeRequest {
            $locked = User::query()->lockForUpdate()->findOrFail($user->id);

            $locked->emailChangeRequests()->pending()->update(['cancelled_at' => now()]);

            $emailChange = $locked->emailChangeRequests()->create([
                'new_email' => $newEmail,
                'token_hash' => EmailChangeRequest::hashToken($token),
                'cancel_token_hash' => EmailChangeRequest::hashToken($cancelToken),
                'expires_at' => now()->addMinutes(config('security.email_change_ttl')),
            ]);

            $this->recordSecurityEvent->execute($locked, SecurityEventType::EmailChangeRequested);

            return $emailChange;
        });

        Notification::route('mail', $newEmail)->notify(
            (new EmailChangeConfirmationNotification($user->name, $token))->afterCommit(),
        );

        $user->notify((new EmailChangeRequestedNotification(
            now(),
            UserAgentLabel::for($this->request->userAgent()),
            $newEmail,
            $cancelToken,
        ))->afterCommit());

        return $emailChange;
    }
}
