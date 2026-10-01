<?php

namespace App\Actions\Account;

use App\Actions\Security\RecordSecurityEventAction;
use App\Enums\SecurityEventType;
use App\Exceptions\Auth\EmailChangeUnavailableException;
use App\Exceptions\Auth\InvalidEmailChangeTokenException;
use App\Models\EmailChangeRequest;
use App\Models\User;
use App\Notifications\EmailChangedNotification;
use App\Support\UserAgentLabel;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Confirmation from the link sent to the new address. It works for a guest
 * too: the token, random with high entropy and valid for 60 minutes, is the
 * only proof needed, as for the email verification.
 *
 * The uniqueness is checked again under lock: the address may have been
 * registered after the request. Then the request is cancelled, so the link
 * cannot be tried again, and the answer is a 409. A registration that
 * commits in between is stopped by the unique index on users.email and
 * handled the same way.
 *
 * The new address is verified by the link itself. Password reset links of
 * the old address stop working. The notice goes on demand to the old
 * address, after the commit.
 *
 * Lock order, as in every action: the user first, then the request. The
 * request is read without lock only to find its user, then read again under
 * lock and checked once more, because it may have changed in between.
 */
final class ConfirmEmailChangeAction
{
    public function __construct(
        private readonly Request $request,
        private readonly RecordSecurityEventAction $recordSecurityEvent,
    ) {}

    public function execute(#[\SensitiveParameter] string $token): User
    {
        try {
            $confirmed = DB::transaction(fn (): ?array => $this->confirm($token));
        } catch (UniqueConstraintViolationException) {
            $confirmed = null;
        }

        if ($confirmed === null) {
            DB::transaction(fn () => $this->lockPending($token)?->forceFill(['cancelled_at' => now()])->save());

            throw new EmailChangeUnavailableException;
        }

        [$user, $oldEmail] = $confirmed;

        Notification::route('mail', $oldEmail)->notify((new EmailChangedNotification(
            $user->name,
            $user->email,
            now(),
            UserAgentLabel::for($this->request->userAgent()),
        ))->afterCommit());

        return $user;
    }

    /**
     * Null when the new address is already taken.
     *
     * @return array{User, string}|null
     */
    private function confirm(#[\SensitiveParameter] string $token): ?array
    {
        $emailChange = $this->lockPending($token) ?? throw new InvalidEmailChangeTokenException;
        $user = $emailChange->user;

        if (User::query()->where('email', $emailChange->new_email)->exists()) {
            return null;
        }

        $oldEmail = $user->email;

        $user->forceFill([
            'email' => $emailChange->new_email,
            'email_verified_at' => now(),
        ])->save();

        $emailChange->forceFill(['confirmed_at' => now()])->save();

        DB::table(config('auth.passwords.users.table'))->where('email', $oldEmail)->delete();

        $this->recordSecurityEvent->execute($user, SecurityEventType::EmailChanged);

        return [$user, $oldEmail];
    }

    /**
     * The pending request of the token with its user, both locked: the user
     * first. Null when the token is wrong or the request is no longer
     * pending, also when it changed between the two reads.
     */
    private function lockPending(#[\SensitiveParameter] string $token): ?EmailChangeRequest
    {
        $hash = EmailChangeRequest::hashToken($token);
        $userId = EmailChangeRequest::query()->where('token_hash', $hash)->pending()->value('user_id');

        if ($userId === null) {
            return null;
        }

        $user = User::query()->lockForUpdate()->findOrFail($userId);

        return EmailChangeRequest::query()
            ->where('token_hash', $hash)
            ->whereBelongsTo($user)
            ->pending()
            ->lockForUpdate()
            ->first()
            ?->setRelation('user', $user);
    }
}
