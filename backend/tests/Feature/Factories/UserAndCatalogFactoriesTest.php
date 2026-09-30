<?php

use App\Enums\UserRole;
use App\Models\Address;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\EmailChangeRequest;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

describe('users', function () {
    it('creates a verified customer with the password change date', function () {
        $user = User::factory()->create()->fresh();

        expect($user->role)->toBe(UserRole::Customer)
            ->and($user->email_verified_at)->not->toBeNull()
            ->and($user->password_changed_at)->not->toBeNull()
            ->and($user->hasSecondFactor())->toBeFalse()
            ->and($user->two_factor_secret)->toBeNull();
    });

    it('creates admins, customers and unverified users', function () {
        expect(User::factory()->admin()->create()->fresh()->role)->toBe(UserRole::Admin)
            ->and(User::factory()->admin()->customer()->create()->fresh()->role)->toBe(UserRole::Customer)
            ->and(User::factory()->unverified()->create()->fresh()->email_verified_at)->toBeNull();
    });

    it('creates a user with confirmed two-factor authentication and eight hashed recovery codes', function () {
        $user = User::factory()->withTwoFactor()->create()->fresh();

        expect($user->hasSecondFactor())->toBeTrue()
            ->and($user->two_factor_secret)->toMatch('/^[A-Z2-7]{32}$/')
            ->and($user->recovery_codes_remaining)->toBe(8)
            ->and(Hash::check('AB12-CD34', $user->two_factor_recovery_codes[0]))->toBeTrue()
            ->and(Hash::check('GH90-JK12', $user->two_factor_recovery_codes[7]))->toBeTrue();
    });

    it('creates a user with a pending email change', function () {
        $user = User::factory()->withPendingEmailChange('nuova@example.com')->create();

        $request = $user->load('pendingEmailChange')->pendingEmailChange;

        expect($request->new_email)->toBe('nuova@example.com')
            ->and($request->token_hash)->toMatch('/^[0-9a-f]{64}$/')
            ->and($request->cancel_token_hash)->toMatch('/^[0-9a-f]{64}$/')
            ->and($user->fresh()->email)->not->toBe('nuova@example.com');
    });

    it('creates email change requests in every state', function () {
        $this->travelTo('2026-09-29 10:00');

        $pending = EmailChangeRequest::factory()->create();
        $expired = EmailChangeRequest::factory()->expired()->create();
        $confirmed = EmailChangeRequest::factory()->confirmed()->create();
        $cancelled = EmailChangeRequest::factory()->cancelled()->create();

        expect($pending->expires_at->format('Y-m-d H:i'))->toBe('2026-09-29 11:00')
            ->and(EmailChangeRequest::pending()->pluck('id')->all())->toBe([$pending->id])
            ->and($expired->expires_at->isPast())->toBeTrue()
            ->and($confirmed->confirmed_at)->not->toBeNull()
            ->and($cancelled->cancelled_at)->not->toBeNull();
    });

    it('creates a security event with a raw user agent', function () {
        $event = SecurityEvent::factory()->create()->fresh();

        expect($event->type->value)->toBe('login')
            ->and($event->user_agent)->toContain('Chrome')
            ->and($event->created_at)->not->toBeNull();
    });
});

describe('catalog', function () {
    it('creates a category with the slug from its name', function () {
        $category = Category::factory()->create();

        expect($category->slug)->toMatch('/^[a-z0-9-]+$/')
            ->and(mb_strlen($category->name))->toBeLessThanOrEqual(50);
    });

    it('creates an active product well above every stock threshold', function () {
        $product = Product::factory()->create()->fresh();

        expect($product->is_active)->toBeTrue()
            ->and($product->stock)->toBeGreaterThan(10)
            ->and($product->availability)->toBe(['status' => 'available', 'label' => 'Disponibile'])
            ->and($product->sku)->toMatch('/^CDS-[A-Z]{3}-\d{3}$/')
            ->and(mb_strlen($product->short_description))->toBeLessThanOrEqual(160)
            ->and($product->price)->toBeBetween(1, 999999)
            ->and(count($product->specifications))->toBeBetween(4, 8)
            ->and($product->specifications[0])->toHaveKeys(['label', 'value']);
    });

    it('creates products in every stock and visibility state', function () {
        expect(Product::factory()->inactive()->create()->is_active)->toBeFalse()
            ->and(Product::factory()->outOfStock()->create()->availability['label'])->toBe('Esaurito')
            ->and(Product::factory()->lastUnits()->create()->availability['label'])->toBe('Ultimi 2 pezzi')
            ->and(Product::factory()->lastUnits(1)->create()->availability['label'])->toBe('Ultimo pezzo')
            ->and(Product::factory()->lowStock()->create()->stock_status['label'])->toBe('Ultimi 4 pezzi')
            ->and(Product::factory()->lowStock()->create()->availability['label'])->toBe('Disponibile')
            ->and(Product::factory()->trashed()->create()->trashed())->toBeTrue();
    });

    it('creates new arrivals and older products', function () {
        $new = Product::factory()->newArrival()->create();
        $older = Product::factory()->older()->create();

        expect($new->is_new)->toBeTrue()
            ->and($older->is_new)->toBeFalse()
            ->and(Product::newArrivals()->pluck('id')->all())->toBe([$new->id]);
    });

    it('creates an image with the product name as alt', function () {
        $product = Product::factory()->create(['name' => 'Cuffie Aria']);

        $image = ProductImage::factory()->for($product)->create();

        $second = ProductImage::factory()->for($product)->create();

        expect($image->alt)->toBe('Cuffie Aria')
            ->and($image->path)->toStartWith('products/')
            ->and($image->position)->toBe(1)
            ->and($second->position)->toBe(2);
    });

    it('creates a valid italian address', function () {
        $address = Address::factory()->create()->fresh();

        expect($address->postal_code)->toMatch('/^\d{5}$/')
            ->and($address->province)->toMatch('/^[A-Z]{2}$/')
            ->and($address->phone)->toMatch('/^\+?[0-9 ]+$/')
            ->and(strlen($address->phone))->toBeLessThanOrEqual(20)
            ->and($address->country)->toBe('IT')
            ->and($address->is_default)->toBeFalse()
            ->and(Address::factory()->asDefault()->create()->is_default)->toBeTrue();
    });

    it('creates a cart row with a quantity between one and three', function () {
        $item = CartItem::factory()->create();

        expect($item->quantity)->toBeBetween(1, 3)
            ->and($item->cart->user)->not->toBeNull();
    });
});
