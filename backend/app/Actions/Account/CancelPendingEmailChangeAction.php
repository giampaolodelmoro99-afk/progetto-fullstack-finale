<?php

namespace App\Actions\Account;

use App\Actions\Security\RecordSecurityEventAction;
use App\Enums\SecurityEventType;
use App\Models\User;
use App\Notifications\EmailChangeCancelledNotification;
use App\Support\UserAgentLabel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Cancellation from the profile of the authenticated user, without identity
 * confirmation: stopping a change is never dangerous. Without a pending
 * request the answer is a 404. The notice goes to the current address.
 */
final class CancelPendingEmailChangeAction
{
    public function __construct(
        private readonly Request $request,
        private readonly RecordSecurityEventAction $recordSecurityEvent,
    ) {}

    public function execute(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $emailChange = $user->emailChangeRequests()
                ->pending()
                ->lockForUpdate()
                ->latest('id')
                ->first() ?? throw new NotFoundHttpException;

            $emailChange->forceFill(['cancelled_at' => now()])->save();

            $this->recordSecurityEvent->execute($user, SecurityEventType::EmailChangeCancelled);
        });

        $user->notify(
            (new EmailChangeCancelledNotification(now(), UserAgentLabel::for($this->request->userAgent())))->afterCommit(),
        );
    }
}
