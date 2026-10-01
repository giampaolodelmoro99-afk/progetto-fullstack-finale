<?php

namespace App\Actions\Security;

use App\Enums\SecurityEventType;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Writes a row of the user's security log with the IP address and the raw
 * user agent of the current request. Metadata never contains secrets.
 */
final class RecordSecurityEventAction
{
    public function __construct(private readonly Request $request) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function execute(User $user, SecurityEventType $type, array $metadata = []): SecurityEvent
    {
        return $user->securityEvents()->create([
            'type' => $type,
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}
