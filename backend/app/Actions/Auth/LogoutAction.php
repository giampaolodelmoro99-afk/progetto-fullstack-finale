<?php

namespace App\Actions\Auth;

use App\Actions\Security\RecordSecurityEventAction;
use App\Enums\SecurityEventType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Ends the current session: the event is written first, while the user is
 * still known, then the session is invalidated with a new CSRF token.
 */
final class LogoutAction
{
    public function __construct(
        private readonly Request $request,
        private readonly RecordSecurityEventAction $recordSecurityEvent,
    ) {}

    public function execute(User $user): void
    {
        $this->recordSecurityEvent->execute($user, SecurityEventType::Logout);

        Auth::guard('web')->logout();

        $this->request->session()->invalidate();
        $this->request->session()->regenerateToken();
    }
}
