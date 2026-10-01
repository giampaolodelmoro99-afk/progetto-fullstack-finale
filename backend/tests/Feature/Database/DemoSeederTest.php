<?php

use App\Models\Address;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderStatusChange;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ProductSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

final class InterruptedProductSeeder extends Seeder
{
    public function run(): void
    {
        throw new RuntimeException('Seeding interrupted');
    }
}

/**
 * The generators of the previous tests are destroyed first: their destructor
 * reseeds mt_rand at random (see DatabaseSeeder).
 */
function seedDemoShop(): void
{
    gc_collect_cycles();

    test()->seed(DatabaseSeeder::class);
}

/**
 * Main demo data without ids, which the auto-increment does not reset.
 *
 * @return array<string, mixed>
 */
function demoShopSnapshot(): array
{
    return [
        'categories' => Category::orderBy('id')->get()
            ->map(fn (Category $category) => [$category->name, $category->slug, $category->description])
            ->all(),
        'products' => Product::with(['category', 'images'])->orderBy('id')->get()
            ->map(fn (Product $product) => [
                $product->sku,
                $product->name,
                $product->slug,
                $product->category->slug,
                $product->price,
                $product->stock,
                $product->is_active,
                $product->specifications,
                $product->created_at->toDateTimeString(),
                $product->images->map(fn ($image) => [$image->path, $image->alt, $image->position])->all(),
            ])
            ->all(),
        'users' => User::orderBy('id')->get()
            ->map(fn (User $user) => [
                $user->name,
                $user->email,
                $user->role->value,
                $user->email_verified_at?->toDateTimeString(),
                $user->two_factor_confirmed_at?->toDateTimeString(),
                $user->created_at->toDateTimeString(),
            ])
            ->all(),
        'addresses' => Address::with('user')->orderBy('id')->get()
            ->map(fn (Address $address) => [
                $address->user->email,
                $address->only(['full_name', 'street', 'city', 'postal_code', 'province', 'country', 'phone', 'is_default']),
            ])
            ->all(),
        'cart' => CartItem::with(['cart.user', 'product'])->orderBy('id')->get()
            ->map(fn (CartItem $item) => [$item->cart->user->email, $item->product->sku, $item->quantity])
            ->all(),
        'orders' => Order::with(['user', 'items', 'statusChanges.changedBy', 'payments'])->orderBy('id')->get()
            ->map(fn (Order $order) => [
                $order->user->email,
                $order->status->value,
                $order->subtotal,
                $order->shipping_cost,
                $order->total,
                $order->shipping_address,
                $order->created_at->toDateTimeString(),
                $order->paid_at?->toDateTimeString(),
                $order->shipped_at?->toDateTimeString(),
                $order->delivered_at?->toDateTimeString(),
                $order->cancelled_at?->toDateTimeString(),
                $order->items->map(fn ($item) => [$item->product_sku, $item->product_name, $item->unit_price, $item->quantity])->all(),
                $order->statusChanges->map(fn (OrderStatusChange $change) => [
                    $change->from_status?->value,
                    $change->to_status->value,
                    $change->changedBy?->email,
                    $change->reason,
                    $change->created_at->toDateTimeString(),
                ])->all(),
                $order->payments->map(fn ($payment) => [$payment->status->value, $payment->amount, $payment->card_last_four])->all(),
            ])
            ->all(),
    ];
}

function wipeDemoShop(): void
{
    foreach ([
        'order_status_changes',
        'payments',
        'order_items',
        'orders',
        'cart_items',
        'carts',
        'addresses',
        'product_images',
        'products',
        'categories',
        'security_events',
        'email_change_requests',
        'users',
    ] as $table) {
        DB::table($table)->delete();
    }
}

beforeEach(function () {
    Storage::fake('public');
    $this->travelTo('2026-09-15 12:00');
});

it('seeds the six categories in menu order, with Viaggio empty', function () {
    seedDemoShop();

    expect(Category::orderBy('id')->pluck('slug')->all())->toBe([
        'audio',
        'ricarica-ed-energia',
        'scrivania',
        'indossabili',
        'casa-smart',
        'viaggio',
    ])
        ->and(Category::where('slug', 'viaggio')->sole()->products()->withTrashed()->count())->toBe(0);
});

it('seeds 50 valid products on the first five categories, four of them inactive', function () {
    seedDemoShop();

    expect(Category::withCount('products')->orderBy('id')->pluck('products_count', 'slug')->all())->toBe([
        'audio' => 16,
        'ricarica-ed-energia' => 10,
        'scrivania' => 10,
        'indossabili' => 8,
        'casa-smart' => 6,
        'viaggio' => 0,
    ])
        ->and(Product::where('is_active', false)->orderBy('sku')->pluck('sku')->all())->toBe([
            'CDS-AUD-004',
            'CDS-AUD-033',
            'CDS-CAS-012',
            'CDS-SCR-012',
        ]);

    foreach (Product::all() as $product) {
        expect($product->sku)->toMatch('/^CDS-(AUD|RIC|SCR|IND|CAS)-\d{3}$/')
            ->and(mb_strlen($product->short_description))->toBeLessThanOrEqual(160)
            ->and($product->description)->toContain("\n\n")
            ->and(count($product->specifications))->toBeGreaterThanOrEqual(4)->toBeLessThanOrEqual(8);
    }
});

it('gives every product three images whose files exist on the public disk', function () {
    seedDemoShop();

    $products = Product::with(['images', 'cover'])->get();

    expect(Storage::disk('public')->allFiles('products'))->toHaveCount(150);

    foreach ($products as $product) {
        expect($product->images->pluck('path')->all())->toBe([
            "products/{$product->slug}-front.webp",
            "products/{$product->slug}-angle.webp",
            "products/{$product->slug}-detail.webp",
        ])
            ->and($product->cover->path)->toBe("products/{$product->slug}-front.webp")
            ->and($product->images->pluck('alt')->unique()->all())->toBe([$product->name]);

        foreach ($product->images as $image) {
            Storage::disk('public')->assertExists($image->path);
        }
    }
});

it('empties the product images folder before copying the images', function () {
    Storage::disk('public')->put('products/orphan.webp', 'orphan');

    seedDemoShop();

    Storage::disk('public')->assertMissing('products/orphan.webp');
    Storage::disk('public')->assertExists('products/cuffie-aria-front.webp');
});

it('seeds the product states shown in the mockups', function () {
    seedDemoShop();

    $cuffieAria = Product::where('slug', 'cuffie-aria')->sole();

    expect($cuffieAria->is_active)->toBeTrue()
        ->and($cuffieAria->stock)->toBeGreaterThan(0)
        ->and($cuffieAria->is_new)->toBeTrue()
        ->and(Product::where('slug', 'cuffie-aria-studio')->sole()->is_active)->toBeTrue()
        ->and(Product::whereIn('slug', ['radio-onda-portatile', 'diffusore-eco-mini', 'orologio-meridiana'])->orderBy('slug')->pluck('stock', 'slug')->all())->toBe([
            'diffusore-eco-mini' => 2,
            'orologio-meridiana' => 2,
            'radio-onda-portatile' => 0,
        ])
        ->and(Product::newArrivals()->orderByDesc('created_at')->pluck('slug')->all())->toBe([
            'orologio-meridiana',
            'caricatore-brezza-65-w',
            'lampada-faro',
            'supporto-colle-in-alluminio',
            'cuffie-aria',
            'auricolari-sussurro-sport',
        ])
        ->and(Product::lowStock()->count())->toBe(6);
});

it('describes the Supporto Colle as anodized aluminium in the finish of its images', function () {
    seedDemoShop();

    $stand = Product::with('cover')->where('slug', 'supporto-colle-in-alluminio')->sole();
    $specifications = collect($stand->specifications)->pluck('value', 'label');

    expect($specifications['Materiali'])->toContain('Alluminio anodizzato')
        ->and($specifications['Colore'])->toBe('Grafite')
        ->and($stand->description)->toContain('grafite')
        ->and(mb_strtolower($stand->description.json_encode($stand->specifications)))->not->toContain('argento');
});

it('seeds the fixed demo accounts and the random customers', function () {
    seedDemoShop();

    $marta = User::where('email', 'marta.lenzi@esempio.it')->sole();
    $giulia = User::with('addresses')->where('email', 'giulia.ferri@esempio.it')->sole();
    $davide = User::where('email', 'davide.conti@esempio.it')->sole();
    $paolo = User::with('addresses')->where('email', 'paolo.greco@esempio.it')->sole();
    $defaultAddress = $giulia->addresses->firstWhere('is_default', true);

    expect($marta->name)->toBe('Marta Lenzi')
        ->and($marta->isAdmin())->toBeTrue()
        ->and($marta->hasSecondFactor())->toBeFalse()
        ->and($giulia->name)->toBe('Giulia Ferri')
        ->and($giulia->email_verified_at)->not->toBeNull()
        ->and($giulia->addresses)->toHaveCount(2)
        ->and($defaultAddress->only(['street', 'postal_code', 'city', 'province', 'country']))->toBe([
            'street' => 'Via San Martino 42',
            'postal_code' => '56125',
            'city' => 'Pisa',
            'province' => 'PI',
            'country' => 'IT',
        ])
        ->and($giulia->addresses->firstWhere('is_default', false)->city)->toBe('Firenze')
        ->and($defaultAddress->created_at->equalTo($giulia->created_at))->toBeTrue()
        ->and($davide->hasSecondFactor())->toBeTrue()
        ->and($davide->two_factor_secret)->toBe('CASADELSILICIODEMOTOTPSEGRETO234')
        ->and($davide->two_factor_secret)->toMatch('/^[A-Z2-7]{32}$/')
        ->and($davide->recovery_codes_remaining)->toBe(8)
        ->and(collect([
            'K7PM-2XQA',
            'R4TD-9WNE',
            'B8HV-3LCJ',
            'M2QF-7YRS',
            'T6ZN-4GKP',
            'W9CX-5DHB',
            'E3JL-8VMT',
            'P5RY-6NAF',
        ])->every(fn (string $code) => collect($davide->two_factor_recovery_codes)->contains(fn (string $hash) => Hash::check($code, $hash))))->toBeTrue()
        ->and($paolo->email_verified_at)->toBeNull()
        ->and($paolo->addresses)->toBeEmpty()
        ->and(User::count())->toBe(24)
        ->and(User::where('role', 'customer')->count())->toBe(23);

    foreach ([$marta, $giulia, $davide, $paolo] as $user) {
        expect(password_verify('Silicio2026', $user->password))->toBeTrue();
    }
});

it('puts an out of stock row in the cart of Giulia', function () {
    seedDemoShop();

    $items = CartItem::with('product')
        ->whereHas('cart.user', fn ($query) => $query->where('email', 'giulia.ferri@esempio.it'))
        ->orderBy('id')
        ->get();

    expect($items->map(fn (CartItem $item) => [$item->product->slug, $item->quantity])->all())->toBe([
        ['cuffie-aria', 1],
        ['caricatore-brezza-65-w', 2],
        ['cavo-seta-usb-c-1-m', 1],
        ['radio-onda-portatile', 1],
    ])
        ->and($items->sum('quantity'))->toBe(5)
        ->and($items->last()->product->stock)->toBe(0)
        ->and($items->last()->is_purchasable)->toBeFalse();
});

it('gives Giulia orders in every state, including a rejected payment', function () {
    seedDemoShop();

    $orders = Order::whereHas('user', fn ($query) => $query->where('email', 'giulia.ferri@esempio.it'))
        ->orderBy('created_at')
        ->get()
        ->map(fn (Order $order) => [$order->status->value, $order->paid_at !== null, $order->total])
        ->all();

    expect($orders)->toBe([
        ['delivered', true, 21900],
        ['cancelled', true, 4590],
        ['delivered', true, 9490],
        ['shipped', true, 34900],
        ['paid', true, 49600],
        ['cancelled', false, 3590],
    ]);
});

it('keeps history, payments and items of every order coherent', function () {
    seedDemoShop();

    $orders = Order::with(['items.product', 'statusChanges.changedBy', 'payments'])->get();
    $marta = User::where('email', 'marta.lenzi@esempio.it')->sole();

    expect($orders->flatMap->statusChanges->where('from_status.value', 'paid')->where('to_status.value', 'cancelled')->pluck('changed_by')->contains($marta->id))->toBeTrue()
        ->and($orders->where('status.value', 'pending'))->toHaveCount(1)
        ->and($orders->filter->failed_payment->count())->toBeGreaterThanOrEqual(1);

    foreach ($orders as $order) {
        $expectedPayments = match (true) {
            $order->status->value === 'pending' => [],
            $order->status->value === 'cancelled' && $order->paid_at === null => ['failed'],
            $order->status->value === 'cancelled' => ['refunded'],
            default => ['succeeded'],
        };

        expect($order->statusChanges->first()->from_status)->toBeNull()
            ->and($order->statusChanges->first()->to_status->value)->toBe('pending')
            ->and($order->statusChanges->last()->to_status)->toBe($order->status)
            ->and($order->payments->map(fn ($payment) => $payment->status->value)->all())->toBe($expectedPayments)
            ->and($order->total)->toBe($order->subtotal + $order->shipping_cost)
            ->and($order->subtotal)->toBe($order->items->sum('line_total'));

        foreach ($order->statusChanges->whereIn('to_status.value', ['shipped', 'delivered']) as $change) {
            expect($change->changedBy->email)->toBe('marta.lenzi@esempio.it');
        }

        foreach ($order->statusChanges->where('from_status.value', 'paid')->where('to_status.value', 'cancelled') as $change) {
            expect($change->changed_by)->toBeIn([$order->user_id, $marta->id]);
        }

        foreach ($order->items as $item) {
            expect($item->product->created_at->lessThanOrEqualTo($order->created_at))->toBeTrue();
        }
    }
});

it('fills the dashboard of the current month with paid orders', function () {
    seedDemoShop();

    $month = Order::paidInMonth('2026-09')->get();
    $byStatus = $month->countBy(fn (Order $order) => $order->status->value);

    expect($month->count())->toBeGreaterThanOrEqual(10)
        ->and($byStatus->get('paid', 0))->toBeGreaterThanOrEqual(1)
        ->and($byStatus->get('shipped', 0))->toBeGreaterThanOrEqual(1)
        ->and($byStatus->get('delivered', 0))->toBeGreaterThanOrEqual(1)
        ->and($byStatus->has('pending'))->toBeFalse()
        ->and($month->where('status.value', '!=', 'cancelled')->sum('total'))->toBeGreaterThan(0);
});

it('pays an order in the current month even when seeding in its first minutes', function () {
    $this->travelTo('2026-10-01 00:30');

    seedDemoShop();

    $order = Order::paidInMonth('2026-10')->sole();

    expect($order->status->value)->toBe('paid')
        ->and($order->paid_at->toDateTimeString())->toBe('2026-10-01 00:15:00')
        ->and($order->created_at->toDateTimeString())->toBe('2026-10-01 00:14:00');
});

it('rolls back the whole seeding when a seeder fails', function () {
    $this->app->bind(ProductSeeder::class, InterruptedProductSeeder::class);

    expect(fn () => seedDemoShop())->toThrow(RuntimeException::class, 'Seeding interrupted');

    $this->assertDatabaseCount('categories', 0);
    $this->assertDatabaseCount('products', 0);
    $this->assertDatabaseCount('users', 0);
});

it('refuses to seed the demo data in production', function () {
    $this->app['env'] = 'production';

    expect(fn () => app(DatabaseSeeder::class)->run())
        ->toThrow(RuntimeException::class, 'The demo data cannot be seeded in production');

    $this->assertDatabaseCount('categories', 0);
    $this->assertDatabaseCount('users', 0);
});

it('produces the same main data on every run with the same date', function () {
    seedDemoShop();
    $first = demoShopSnapshot();

    wipeDemoShop();
    seedDemoShop();

    expect(demoShopSnapshot())->toBe($first)
        ->and($first['orders'])->not->toBeEmpty();
});
