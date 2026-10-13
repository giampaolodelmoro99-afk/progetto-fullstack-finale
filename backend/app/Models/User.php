<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\PasskeyAuthenticatable;

#[Fillable(['name', 'email', 'password', 'password_changed_at'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable;

    /**
     * Name stored by the account deletion, neutral with respect to the role.
     */
    public const ANONYMIZED_NAME = 'Utente eliminato';

    /**
     * Label the admin area shows instead of an anonymized customer.
     */
    public const DELETED_CUSTOMER_LABEL = 'Cliente eliminato';

    /**
     * Label the admin area shows instead of an admin removed by admin:remove.
     */
    public const DELETED_ADMIN_LABEL = 'Amministratore eliminato';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'customer',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'password_changed_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_last_used_step' => 'integer',
            'anonymized_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Address, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * @return HasOne<Cart, $this>
     */
    public function cart(): HasOne
    {
        return $this->hasOne(Cart::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<EmailChangeRequest, $this>
     */
    public function emailChangeRequests(): HasMany
    {
        return $this->hasMany(EmailChangeRequest::class);
    }

    /**
     * The most recent request that is not confirmed, not cancelled and not expired.
     *
     * @return HasOne<EmailChangeRequest, $this>
     */
    public function pendingEmailChange(): HasOne
    {
        return $this->hasOne(EmailChangeRequest::class)->ofMany(
            ['id' => 'max'],
            fn (Builder $query) => $query->pending(),
        );
    }

    /**
     * @return HasMany<SecurityEvent, $this>
     */
    public function securityEvents(): HasMany
    {
        return $this->hasMany(SecurityEvent::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * The customer deleted the account, or the admin was removed with
     * admin:remove: the user stays, anonymized, only for the orders and the
     * status history kept by the retention obligation, and cannot log in.
     */
    public function isAnonymized(): bool
    {
        return $this->anonymized_at !== null;
    }

    /**
     * The name of the user in the admin area: for an anonymized account the
     * label "Amministratore eliminato" or "Cliente eliminato" by role, never
     * the stored name.
     */
    public function adminLabel(): string
    {
        if (! $this->isAnonymized()) {
            return $this->name;
        }

        return $this->isAdmin() ? self::DELETED_ADMIN_LABEL : self::DELETED_CUSTOMER_LABEL;
    }

    /**
     * Fictitious and unique address of the anonymized account: the reserved
     * .invalid domain never receives mail, and registration and email change
     * refuse it, so nobody can take it first.
     */
    public function anonymizedEmail(): string
    {
        return "deleted-{$this->id}@anonymized.invalid";
    }

    /**
     * Two-factor authentication is active only once the TOTP is confirmed.
     * Passkeys never turn it on: they always allow the passwordless login,
     * and count as a second factor only when the TOTP is active.
     */
    public function hasSecondFactor(): bool
    {
        return $this->two_factor_confirmed_at !== null;
    }

    /**
     * Second factor methods offered by the login challenge and the password
     * reset; empty without two-factor authentication. The passkey is offered
     * only when the user has registered at least one.
     *
     * @return list<string>
     */
    public function secondFactorMethods(): array
    {
        if (! $this->hasSecondFactor()) {
            return [];
        }

        return $this->hasPasskeysEnabled() ? ['totp', 'recovery_code', 'passkey'] : ['totp', 'recovery_code'];
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }

    /**
     * @param  string  $token
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * @return Attribute<int, never>
     */
    protected function recoveryCodesRemaining(): Attribute
    {
        return Attribute::get(
            fn (): int => $this->hasSecondFactor() ? count($this->two_factor_recovery_codes ?? []) : 0,
        );
    }
}
