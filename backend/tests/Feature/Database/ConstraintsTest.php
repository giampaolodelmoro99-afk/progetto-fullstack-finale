<?php

use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\EmailChangeRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

describe('uniqueness', function () {
    it('rejects a duplicate sku, also against a soft deleted product', function () {
        Product::factory()->trashed()->create(['sku' => 'CDS-AUD-001']);

        Product::factory()->create(['sku' => 'CDS-AUD-001']);
    })->throws(UniqueConstraintViolationException::class);

    it('rejects a duplicate category name even with a different slug', function () {
        Category::factory()->create(['name' => 'Audio']);

        DB::table('categories')->insert(['name' => 'Audio', 'slug' => 'audio-2']);
    })->throws(UniqueConstraintViolationException::class);

    it('rejects a duplicate category slug even with a different name', function () {
        Category::factory()->create(['name' => 'Audio']);

        DB::table('categories')->insert(['name' => 'Audio 2', 'slug' => 'audio']);
    })->throws(UniqueConstraintViolationException::class);

    it('rejects a second cart for the same user', function () {
        $user = User::factory()->create();
        Cart::factory()->for($user)->create();

        Cart::factory()->for($user)->create();
    })->throws(UniqueConstraintViolationException::class);

    it('rejects the same product twice in a cart', function () {
        $cart = Cart::factory()->create();
        $product = Product::factory()->create();
        CartItem::factory()->for($cart)->for($product)->create();

        CartItem::factory()->for($cart)->for($product)->create();
    })->throws(UniqueConstraintViolationException::class);

    it('rejects a duplicate order number', function () {
        $order = Order::factory()->create();

        Order::factory()->create()->update(['number' => $order->number]);
    })->throws(UniqueConstraintViolationException::class);

    it('rejects a duplicate email change token hash', function () {
        $hash = str_repeat('a', 64);
        EmailChangeRequest::factory()->create(['token_hash' => $hash]);

        EmailChangeRequest::factory()->create(['token_hash' => $hash]);
    })->throws(UniqueConstraintViolationException::class);

    it('allows two images of a product in the same position', function () {
        $product = Product::factory()->create();
        ProductImage::factory()->for($product)->count(2)->create(['position' => 1]);

        expect($product->images()->count())->toBe(2);
    });
});

describe('deletion', function () {
    it('cascades from a user to addresses, cart, email change requests and security events', function () {
        $user = User::factory()->withPendingEmailChange('nuova@example.com')->create();
        Address::factory()->for($user)->create();
        Cart::factory()->for($user)->create();
        SecurityEvent::factory()->for($user)->create();

        $user->delete();

        expect(Address::count())->toBe(0)
            ->and(Cart::count())->toBe(0)
            ->and(EmailChangeRequest::count())->toBe(0)
            ->and(SecurityEvent::count())->toBe(0);
    });

    it('does not delete a user who has orders', function () {
        $order = Order::factory()->create();

        User::find($order->user_id)->delete();
    })->throws(QueryException::class);

    it('does not delete a user who authored a status change', function () {
        $admin = User::factory()->admin()->create();
        Order::factory()->shipped($admin)->create();

        $admin->delete();
    })->throws(QueryException::class);

    it('does not delete a category with a soft deleted product', function () {
        $category = Category::factory()->create();
        Product::factory()->for($category)->trashed()->create();

        $category->delete();
    })->throws(QueryException::class);

    it('deletes a category without products', function () {
        $category = Category::factory()->create();

        $category->delete();

        expect(Category::count())->toBe(0);
    });

    it('does not delete an order with payments', function () {
        $order = Order::factory()->paid()->create();

        $order->delete();
    })->throws(QueryException::class);

    it('cascades from an order without payments to items and history', function () {
        $order = Order::factory()->pending()->create();

        $order->delete();

        expect(OrderItem::count())->toBe(0)
            ->and(DB::table('order_status_changes')->count())->toBe(0);
    });

    it('keeps order items with a null product when the product is force deleted', function () {
        $order = Order::factory()->create();
        $item = $order->items()->first();
        $product = Product::find($item->product_id);
        ProductImage::factory()->for($product)->create();
        CartItem::factory()->for($product)->create();

        $product->forceDelete();

        expect($item->fresh()->product_id)->toBeNull()
            ->and($item->fresh()->product_name)->toBe($product->name)
            ->and(ProductImage::count())->toBe(0)
            ->and(CartItem::count())->toBe(0);
    });

    it('keeps images, cart rows and order items when the product is soft deleted', function () {
        $order = Order::factory()->create();
        $product = Product::find($order->items()->first()->product_id);
        ProductImage::factory()->for($product)->create();
        CartItem::factory()->for($product)->create();

        $product->delete();

        expect(ProductImage::count())->toBe(1)
            ->and(CartItem::count())->toBe(1)
            ->and(OrderItem::first()->product_id)->toBe($product->id);
    });

    it('cascades from a cart to its items', function () {
        $cart = Cart::factory()->create();
        CartItem::factory()->for($cart)->count(2)->create();

        $cart->delete();

        expect(CartItem::count())->toBe(0);
    });
});

describe('dates without default', function () {
    it('rejects a status change inserted without created_at', function () {
        $order = Order::factory()->create();

        DB::table('order_status_changes')->insert([
            'order_id' => $order->id,
            'from_status' => OrderStatus::Pending->value,
            'to_status' => OrderStatus::Paid->value,
        ]);
    })->throws(QueryException::class);

    it('rejects a security event inserted without created_at', function () {
        $user = User::factory()->create();

        DB::table('security_events')->insert([
            'user_id' => $user->id,
            'type' => 'login',
        ]);
    })->throws(QueryException::class);

    it('lets eloquent fill created_at in the application timezone', function () {
        $this->travelTo('2026-09-29 16:52:00');
        $user = User::factory()->create();

        $event = $user->securityEvents()->create(['type' => 'login']);

        expect(DB::table('security_events')->where('id', $event->id)->value('created_at'))
            ->toBe('2026-09-29 16:52:00');
    });
});
