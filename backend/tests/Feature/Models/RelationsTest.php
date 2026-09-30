<?php

use App\Enums\PaymentStatus;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\EmailChangeRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SecurityEvent;
use App\Models\User;

it('uses the first image by position as the cover', function () {
    $product = Product::factory()->create();
    ProductImage::factory()->for($product)->create(['position' => 2, 'path' => 'products/second.webp']);
    ProductImage::factory()->for($product)->create(['position' => 1, 'path' => 'products/first.webp']);
    ProductImage::factory()->for($product)->create(['position' => 3, 'path' => 'products/third.webp']);

    $product->load(['cover', 'images']);

    expect($product->cover->path)->toBe('products/first.webp')
        ->and($product->images->pluck('path')->all())
        ->toBe(['products/first.webp', 'products/second.webp', 'products/third.webp']);
});

it('has no cover without images', function () {
    expect(Product::factory()->create()->load('cover')->cover)->toBeNull();
});

it('keeps the soft deleted product of a cart row', function () {
    $item = CartItem::factory()->create();
    Product::find($item->product_id)->delete();

    $item = CartItem::with('product')->find($item->id);

    expect($item->product)->not->toBeNull()
        ->and($item->is_purchasable)->toBeFalse();
});

it('hides the soft deleted product of an order row', function () {
    $order = Order::factory()->create();
    Product::find($order->items()->first()->product_id)->delete();

    expect(OrderItem::with('product')->first()->product)->toBeNull();
});

it('orders the status history by creation time, then id', function () {
    $order = Order::factory()->delivered()->create();

    $statuses = $order->statusChanges()->get()->map(fn ($change) => $change->to_status->value)->all();

    expect($statuses)->toBe(['pending', 'paid', 'shipped', 'delivered']);
});

it('finds the pending email change of a user', function () {
    $user = User::factory()->create();
    EmailChangeRequest::factory()->for($user)->expired()->create(['new_email' => 'scaduta@example.com']);
    EmailChangeRequest::factory()->for($user)->cancelled()->create(['new_email' => 'annullata@example.com']);
    EmailChangeRequest::factory()->for($user)->create(['new_email' => 'in-attesa@example.com']);
    EmailChangeRequest::factory()->for($user)->confirmed()->create(['new_email' => 'confermata@example.com']);

    expect($user->load('pendingEmailChange')->pendingEmailChange->new_email)->toBe('in-attesa@example.com');
});

it('has no pending email change when every request is closed', function () {
    $user = User::factory()->create();
    EmailChangeRequest::factory()->for($user)->expired()->create();
    EmailChangeRequest::factory()->for($user)->confirmed()->create();

    expect($user->load('pendingEmailChange')->pendingEmailChange)->toBeNull();
});

it('considers a request pending until it expires', function () {
    $this->travelTo('2026-09-29 10:00');
    $user = User::factory()->withPendingEmailChange('nuova@example.com')->create();

    $this->travelTo('2026-09-29 10:59');
    expect(EmailChangeRequest::pending()->count())->toBe(1);

    $this->travelTo('2026-09-29 11:01');
    expect(EmailChangeRequest::pending()->count())->toBe(0)
        ->and($user->load('pendingEmailChange')->pendingEmailChange)->toBeNull();
});

it('uses the most recent payment as the latest one', function () {
    $this->travelTo('2026-09-10 10:00');
    $order = Order::factory()->create();
    $failed = Payment::factory()->for($order)->failed()->create();

    $this->travelTo('2026-09-10 10:05');
    $succeeded = Payment::factory()->for($order)->create();

    $latest = $order->load('latestPayment')->latestPayment;

    expect($latest->id)->toBe($succeeded->id)
        ->and($latest->status)->toBe(PaymentStatus::Succeeded)
        ->and($latest->id)->not->toBe($failed->id);
});

it('links a user to addresses, cart, orders, email change requests and security events', function () {
    $user = User::factory()->create();
    $address = Address::factory()->for($user)->create();
    $cart = Cart::factory()->for($user)->create();
    $order = Order::factory()->for($user)->create();
    $request = EmailChangeRequest::factory()->for($user)->create();
    $event = SecurityEvent::factory()->for($user)->create();
    Order::factory()->create();

    $user->load(['addresses', 'cart', 'orders', 'emailChangeRequests', 'securityEvents']);

    expect($user->addresses->pluck('id')->all())->toBe([$address->id])
        ->and($user->cart->id)->toBe($cart->id)
        ->and($user->orders->pluck('id')->all())->toBe([$order->id])
        ->and($user->emailChangeRequests->pluck('id')->all())->toBe([$request->id])
        ->and($user->securityEvents->pluck('id')->all())->toBe([$event->id]);
});

it('links products, categories, images, carts and orders in both directions', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->for($category)->create();
    $image = ProductImage::factory()->for($product)->create();
    $item = CartItem::factory()->for($product)->create();
    $order = Order::factory()->create();
    $payment = Payment::factory()->for($order)->create();

    expect($product->category->id)->toBe($category->id)
        ->and($category->products->pluck('id')->all())->toBe([$product->id])
        ->and($image->product->id)->toBe($product->id)
        ->and($item->cart->items->pluck('id')->all())->toBe([$item->id])
        ->and($order->user->id)->toBe($order->user_id)
        ->and($order->items->first()->order->id)->toBe($order->id)
        ->and($payment->order->id)->toBe($order->id)
        ->and($order->statusChanges->first()->order->id)->toBe($order->id)
        ->and(SecurityEvent::factory()->create()->user)->not->toBeNull();
});
