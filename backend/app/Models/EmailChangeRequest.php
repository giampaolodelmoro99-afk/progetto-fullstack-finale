<?php

namespace App\Models;

use Database\Factories\EmailChangeRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['new_email', 'token_hash', 'cancel_token_hash', 'expires_at', 'confirmed_at', 'cancelled_at'])]
#[Hidden(['token_hash', 'cancel_token_hash'])]
class EmailChangeRequest extends Model
{
    /** @use HasFactory<EmailChangeRequestFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * SHA-256 in hexadecimal: the tokens are random with high entropy, so a
     * fast hash without salt is enough, and the request can be looked up by
     * its unique index even by a guest (N7).
     */
    public static function hashToken(#[\SensitiveParameter] string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Not confirmed, not cancelled and not expired.
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->whereNull('confirmed_at')
            ->whereNull('cancelled_at')
            ->where('expires_at', '>', now());
    }
}
