<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Client;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Faker\Factory as FakerFactory;

class FakeCashSalesSeeder extends Seeder
{
    public function run(): void
    {
        $faker = FakerFactory::create();

        // 1) Truncate only Sales & SaleItems so we don't lose clients/products
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');
SaleItem::truncate();
DB::statement('SET FOREIGN_KEY_CHECKS = 1;');

        // 2) Ensure at least 10 products exist
        $category = Category::first() ?: Category::factory()->create();
        $provider = Product::first()
            ? Product::first()->provider
            : Provider::factory()->create();
        if (Product::count() < 10) {
            Product::factory(10)->create([
                'category_id' => $category->id,
                'provider_id' => $provider->id,
            ]);
        }
        $products = Product::all()->take(10);

        // 3) Ensure at least 10 clients exist
        $clients = Client::all();
        if ($clients->count() < 10) {
            $this->command->warn('You need at least 10 clients.');
            return;
        }
        $clients = $clients->take(10);

        $discountOptions = [0, 50, 100, 150];

        // 4) For each of the past 6 months, create one cash sale per client
        $monthsBack = 6;
        for ($i = 0; $i < $monthsBack; $i++) {
            $monthBase = now()->subMonths($i)->startOfMonth();

            foreach ($clients as $client) {
                $product     = $products->random();
                $quantity    = 1;
                $unitPrice   = $product->cash_price;
                $subtotal    = $quantity * $unitPrice;
                $discount    = $faker->randomElement($discountOptions);
                $finalPrice  = $subtotal - $discount;

                // pick a payment day in the month
                $day = rand(1, 28);
                $scheduled = $monthBase->copy()->setDay($day);

                // 70% chance paid on-time, else late by 5–10 days
                $offset = $faker->boolean(70)
                    ? 0
                    : $faker->numberBetween(5, 10);
                $paidDate = $scheduled->copy()->addDays($offset);
                if ($paidDate->gt(now())) {
                    $paidDate = now();
                }
                $dateStr = $paidDate->format('Y-m-d');

                // ---- Create Sale ----
                $sale = Sale::create([
                    'client_id'             => $client->id,
                    'sale_type'             => 'cash',
                    'total_price'           => $subtotal,
                    'discount'              => $discount,
                    'interest_rate'         => 0.0,
                    'interest_amount'       => 0.0,
                    'final_price'           => $finalPrice,
                    'down_payment'          => $finalPrice,
                    'monthly_installment'   => $finalPrice,
                    'remaining_amount'      => 0.0,
                    'months_count'          => 1,
                    'payment_dates'         => [$dateStr],
                    'payment_amounts'       => [$finalPrice],
                    'status'                => 'completed',
                    'notes'                 => null,
                    'preferred_payment_day' => $day,
                    'next_payment_date'     => null,
                    'created_at'            => $dateStr,
                    'updated_at'            => $dateStr,
                ]);

                // ---- Create SaleItem ----
                SaleItem::create([
                    'sale_id'    => $sale->id,
                    'product_id' => $product->id,
                    'quantity'   => $quantity,
                    'unit_price' => $unitPrice,
                ]);
            }
        }

        $this->command->info("✅ Seeded {$monthsBack} months × 10 clients = " . ($monthsBack * 10) . " cash sales.");
    }
}
