<?php

use App\Actions\Cart\AddCartItemAction;
use App\Actions\Cart\ClearCartAction;
use App\Actions\Cart\RemoveCartItemAction;
use App\Actions\Cart\UpdateCartItemAction;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

it('adds nothing and creates no cart for an account anonymized in the meantime', function () {
    $user = User::factory()->create();
    $product = shopProduct();
    anonymizeBehindTheSession($user);

    expect(fn () => app(AddCartItemAction::class)->execute($user, $product->id, 1))
        ->toThrow(AuthenticationException::class);

    expect(Cart::query()->count())->toBe(0)
        ->and(CartItem::query()->count())->toBe(0);
});

it('changes no quantity for an account anonymized in the meantime', function () {
    $user = User::factory()->create();
    $row = cartRow($user, shopProduct(), 1);
    anonymizeBehindTheSession($user);

    expect(fn () => app(UpdateCartItemAction::class)->execute($user, $row, 3))
        ->toThrow(AuthenticationException::class);

    expect($row->fresh()->quantity)->toBe(1);
});

it('removes no row for an account anonymized in the meantime', function () {
    $user = User::factory()->create();
    $row = cartRow($user, shopProduct(), 1);
    anonymizeBehindTheSession($user);

    expect(fn () => app(RemoveCartItemAction::class)->execute($user, $row))
        ->toThrow(AuthenticationException::class);

    $this->assertModelExists($row);
});

it('empties no cart for an account anonymized in the meantime', function () {
    $user = User::factory()->create();
    $row = cartRow($user, shopProduct(), 2);
    anonymizeBehindTheSession($user);

    expect(fn () => app(ClearCartAction::class)->execute($user))
        ->toThrow(AuthenticationException::class);

    $this->assertModelExists($row);
});

it('answers 401 to an anonymized account that still has a session', function () {
    actingAsCustomer(User::factory()->anonymized()->create());

    fromFrontend()->postJson('/api/v1/cart/items', ['product_id' => shopProduct()->id, 'quantity' => 1])
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'Devi accedere per continuare.']);

    expect(Cart::query()->count())->toBe(0);
});

it('answers 404 when the row was removed by another request', function () {
    $user = actingAsCustomer();
    $row = cartRow($user, shopProduct(), 1);
    CartItem::query()->whereKey($row->id)->delete();

    expect(fn () => app(UpdateCartItemAction::class)->execute($user, $row, 2))
        ->toThrow(ModelNotFoundException::class);
});

it('does nothing when the row was already removed by another request', function () {
    $user = User::factory()->create();
    $removed = cartRow($user, shopProduct(), 1);
    $kept = cartRow($user, shopProduct(), 2);
    CartItem::query()->whereKey($removed->id)->delete();

    app(RemoveCartItemAction::class)->execute($user, $removed);

    $this->assertModelExists($kept);
    expect($kept->fresh()->quantity)->toBe(2);
});
