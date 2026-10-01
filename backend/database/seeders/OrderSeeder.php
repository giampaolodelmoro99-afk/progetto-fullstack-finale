<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Seeder;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Orders of the last six months, built with the OrderFactory states so that
 * history rows and payments are always coherent. Giulia has fixed orders in
 * every state; the other orders are drawn by a Randomizer with its own seed
 * (see DatabaseSeeder for why not Faker), with a state that depends on their
 * age (recent orders are still to ship, old
 * ones are delivered). Marta Lenzi is the author of every admin transition.
 * Stock is left untouched: these are past orders.
 */
class OrderSeeder extends Seeder
{
    private const FAKER_SEED = 4242;

    private const RANDOM_SEED = 4242;

    private const HISTORY_DAYS = 180;

    private const MINUTES_PER_DAY = 1440;

    private User $admin;

    /**
     * @var Collection<int, Product>
     */
    private Collection $products;

    /**
     * @var Collection<string, Product>
     */
    private Collection $productsBySku;

    /**
     * @var Collection<int, Address>
     */
    private Collection $defaultAddresses;

    private Randomizer $random;

    public function run(): void
    {
        // Faker only fills the attributes generated inside the factories.
        fake()->seed(self::FAKER_SEED);
        fake()->unique(true);
        $this->random = new Randomizer(new Mt19937(self::RANDOM_SEED));

        $this->admin = User::where('email', 'marta.lenzi@esempio.it')->firstOrFail();
        $this->products = Product::orderBy('id')->get();
        $this->productsBySku = $this->products->keyBy('sku');
        $this->defaultAddresses = Address::where('is_default', true)->get()->keyBy('user_id');

        $this->seedGiuliaOrders();
        $this->seedCurrentMonthOrder();
        $this->seedHistory();
    }

    /**
     * A paid order halfway between the start of the current month and now, so
     * the dashboard of the month is never empty, even when seeding in the
     * first minutes of the month.
     */
    private function seedCurrentMonthOrder(): void
    {
        $startOfMonth = now()->startOfMonth();
        $paidAt = $startOfMonth->copy()->addSeconds(intdiv((int) $startOfMonth->diffInSeconds(now()), 2));

        $this->createOrder(
            User::where('email', 'davide.conti@esempio.it')->firstOrFail(),
            $paidAt->copy()->subMinute(),
            'paid',
            ['CDS-SCR-020' => 1],
        );
    }

    /**
     * One order per state, in the spirit of the account mockup, plus a
     * rejected payment visible only to the admin.
     */
    private function seedGiuliaOrders(): void
    {
        $giulia = User::where('email', 'giulia.ferri@esempio.it')->firstOrFail();

        $this->createOrder($giulia, today()->subDays(120)->setTime(18, 20), 'delivered', ['CDS-AUD-010' => 1]);
        $this->createOrder($giulia, today()->subDays(40)->setTime(11, 5), 'cancelled_by_customer', ['CDS-RIC-002' => 1]);
        $this->createOrder($giulia, today()->subDays(28)->setTime(21, 14), 'delivered', ['CDS-RIC-010' => 1, 'CDS-SCR-030' => 1]);
        $this->createOrder($giulia, today()->subDays(3)->setTime(10, 30), 'shipped', ['CDS-AUD-001' => 1]);
        $this->createOrder($giulia, today()->subDays(1)->setTime(18, 41), 'paid', ['CDS-AUD-001' => 1, 'CDS-RIC-001' => 2, 'CDS-RIC-010' => 1]);
        $this->createOrder($giulia, now()->subHours(2)->startOfMinute(), 'failed', ['CDS-RIC-010' => 1]);
    }

    /**
     * Up to one order a day over the last six months, up to two a day in the
     * last 30 days, plus one order whose payment is still in progress.
     */
    private function seedHistory(): void
    {
        $customers = User::where('role', UserRole::Customer)
            ->whereNotNull('email_verified_at')
            ->where('email', '!=', 'giulia.ferri@esempio.it')
            ->whereHas('addresses')
            ->orderBy('id')
            ->get()
            ->all();
        $latest = now()->subMinutes(30);

        for ($daysAgo = self::HISTORY_DAYS; $daysAgo >= 0; $daysAgo--) {
            $count = $this->random->getInt(0, $daysAgo <= 30 ? 2 : 1);

            for ($i = 0; $i < $count; $i++) {
                $createdAt = today()->subDays($daysAgo)->setTime($this->random->getInt(8, 21), $this->random->getInt(0, 59));
                $customer = $this->pick($customers);
                $state = $this->stateFor((int) $createdAt->diffInMinutes(now()));
                $lines = $this->randomLines($createdAt);

                if ($createdAt->greaterThan($latest) || $lines === []) {
                    continue;
                }

                $this->createOrder($customer, $createdAt, $state, $lines);
            }
        }

        $createdAt = now()->subMinutes(10)->startOfMinute();
        $this->createOrder($this->pick($customers), $createdAt, 'pending', $this->randomLines($createdAt));
    }

    /**
     * Each state needs enough time behind it: shipped and cancelled one day
     * after payment, delivered two days after shipping.
     */
    private function stateFor(int $ageInMinutes): string
    {
        $roll = $this->random->getInt(1, 100);
        $cancelled = $this->random->getInt(1, 100) <= 70 ? 'cancelled_by_customer' : 'cancelled_by_admin';

        return match (true) {
            $ageInMinutes >= 10 * self::MINUTES_PER_DAY => match (true) {
                $roll <= 85 => 'delivered',
                $roll <= 92 => $cancelled,
                default => 'failed',
            },
            $ageInMinutes >= 3 * self::MINUTES_PER_DAY + 5 => match (true) {
                $roll <= 40 => 'delivered',
                $roll <= 80 => 'shipped',
                $roll <= 88 => $cancelled,
                $roll <= 95 => 'failed',
                default => 'paid',
            },
            $ageInMinutes >= self::MINUTES_PER_DAY + 5 => match (true) {
                $roll <= 45 => 'paid',
                $roll <= 85 => 'shipped',
                $roll <= 95 => 'failed',
                default => $cancelled,
            },
            default => $roll <= 90 ? 'paid' : 'failed',
        };
    }

    /**
     * From one to three products that already existed when the order was placed.
     *
     * @return array<string, int> quantities by SKU
     */
    private function randomLines(CarbonInterface $createdAt): array
    {
        $available = $this->products->filter(fn (Product $product) => $product->created_at->lessThan($createdAt))->values()->all();

        if ($available === []) {
            return [];
        }

        $lines = [];

        foreach ($this->random->pickArrayKeys($available, $this->random->getInt(1, min(3, count($available)))) as $key) {
            $lines[$available[$key]->sku] = $this->random->getInt(1, 2);
        }

        return $lines;
    }

    /**
     * @param  array<string, int>  $lines  quantities by SKU
     */
    private function createOrder(User $customer, CarbonInterface $createdAt, string $state, array $lines): Order
    {
        $items = OrderItem::factory()
            ->count(count($lines))
            ->sequence(...array_map(
                fn (string $sku, int $quantity): array => ['product_id' => $this->productsBySku[$sku]->id, 'quantity' => $quantity],
                array_keys($lines),
                $lines,
            ));

        return $this->factoryFor($state, $customer)
            ->for($customer)
            ->has($items, 'items')
            ->create([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
                'shipping_address' => $this->addressSnapshot($customer),
            ]);
    }

    /**
     * @return Factory<Order>
     */
    private function factoryFor(string $state, User $customer): Factory
    {
        return match ($state) {
            'pending' => Order::factory()->pending(),
            'paid' => Order::factory()->paid(),
            'shipped' => Order::factory()->shipped($this->admin),
            'delivered' => Order::factory()->delivered($this->admin),
            'cancelled_by_customer' => Order::factory()->cancelledAfterPayment($customer),
            'cancelled_by_admin' => Order::factory()->cancelledAfterPayment($this->admin),
            'failed' => Order::factory()->failedPayment(),
        };
    }

    /**
     * @return array<string, string>
     */
    private function addressSnapshot(User $customer): array
    {
        return $this->defaultAddresses[$customer->id]->only(['full_name', 'street', 'postal_code', 'city', 'province', 'country', 'phone']);
    }

    /**
     * @template T
     *
     * @param  list<T>  $items
     * @return T
     */
    private function pick(array $items): mixed
    {
        return $items[$this->random->pickArrayKeys($items, 1)[0]];
    }
}
