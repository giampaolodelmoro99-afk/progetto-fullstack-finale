<?php

namespace App\Actions\Account;

use App\Actions\Security\RecordSecurityEventAction;
use App\Enums\SecurityEventType;
use App\Exceptions\Auth\InvalidEmailChangeTokenException;
use App\Models\EmailChangeRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cancellation from the link sent to the old address, even by a guest: the
 * owner of the old address may not be able to log in. A request already
 * confirmed cannot be cancelled: the notice of the changed address points
 * to the support.
 *
 * Lock order, as in every action: the user first, then the request. The
 * request is read without lock only to find its user, then read again under
 * lock and checked once more, because it may have changed in between.
 */
final class CancelEmailChangeByTokenAction
{
    public function __construct(private readonly RecordSecurityEventAction $recordSecurityEvent) {}

    public function execute(#[\SensitiveParameter] string $token): void
    {
        $hash = EmailChangeRequest::hashToken($token);

        DB::transaction(function () use ($hash): void {
            $userId = EmailChangeRequest::query()->where('cancel_token_hash', $hash)->pending()->value('user_id')
                ?? throw new InvalidEmailChangeTokenException;

            $user = User::query()->lockForUpdate()->findOrFail($userId);

            $emailChange = EmailChangeRequest::query()
                ->where('cancel_token_hash', $hash)
                ->whereBelongsTo($user)
                ->pending()
                ->lockForUpdate()
                ->first() ?? throw new InvalidEmailChangeTokenException;

            $emailChange->forceFill(['cancelled_at' => now()])->save();

            $this->recordSecurityEvent->execute($user, SecurityEventType::EmailChangeCancelled);
        });
    }
}
