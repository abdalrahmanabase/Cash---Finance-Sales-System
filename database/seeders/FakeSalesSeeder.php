<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Client;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Provider;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Faker\Factory as FakerFactory;

class FakeSalesSeeder extends Seeder
{
    public function run(): void
    {
        $faker = FakerFactory::create();

        // 1) Wipe out old sales & items (once)
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');
        SaleItem::truncate();
        Sale::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');

        // 2) Ensure at least 10 products exist
        $category = Category::first() ?: Category::factory()->create();
        $provider = Provider::first() ?: Provider::factory()->create();
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

        // 4) For each of the past 6 months...
        $monthsBack = 6;
        for ($i = 0; $i < $monthsBack; $i++) {
            $monthBase = now()->subMonths($i)->startOfMonth();

            foreach ($clients as $client) {
                $product   = $products->random();
                $quantity  = 1;
                $cashPrice = $product->cash_price;
                $subtotal  = $quantity * $cashPrice;

                //
                // A) CASH SALE
                //
                $discountCash  = $faker->randomElement($discountOptions);
                $finalCash     = $subtotal - $discountCash;
                $dayCash       = rand(1, 28);
                $scheduledCash = $monthBase->copy()->setDay($dayCash);
                $offsetCash    = $faker->boolean(70) ? 0 : $faker->numberBetween(5, 10);
                $paidDateCash  = $scheduledCash->copy()->addDays($offsetCash)->min(now());
                $dateCashStr   = $paidDateCash->format('Y-m-d');

                $saleCash = Sale::create([
                    'client_id'             => $client->id,
                    'sale_type'             => 'cash',
                    'total_price'           => $subtotal,
                    'discount'              => $discountCash,
                    'interest_rate'         => 0.0,
                    'interest_amount'       => 0.0,
                    'final_price'           => $finalCash,
                    'down_payment'          => $finalCash,
                    'monthly_installment'   => $finalCash,
                    'remaining_amount'      => 0.0,
                    'months_count'          => 1,
                    'payment_dates'         => [$dateCashStr],
                    'payment_amounts'       => [$finalCash],
                    'status'                => 'completed',
                    'notes'                 => null,
                    'preferred_payment_day' => $dayCash,
                    'next_payment_date'     => null,
                    'created_at'            => $paidDateCash->format('Y-m-d'),
                ]);

                SaleItem::create([
                    'sale_id'    => $saleCash->id,
                    'product_id' => $product->id,
                    'quantity'   => $quantity,
                    'unit_price' => $cashPrice,
                ]);

                //
                // B) INSTALLMENT SALE
                //
                $discountInst      = $faker->randomElement($discountOptions);
                $finalInstSubtotal = $subtotal - $discountInst;
                $downInst          = round($finalInstSubtotal * 0.3, 2);
                $toFinance         = $finalInstSubtotal - $downInst;
                $monthsCount       = 12;
                $rateInst          = $faker->randomFloat(2, 5, 15);
                $interestInst      = round($toFinance * $rateInst / 100, 2);
                $totalFinance      = $toFinance + $interestInst;
                $monthlyInst       = round($totalFinance / $monthsCount, 2);
                $dayInst           = rand(1, 28);
                $startInst         = $monthBase->copy()->setDay($dayInst);

                $payDates = [];
                $payAmts  = [];

                // Down payment
                $payDates[] = $startInst->format('Y-m-d');
                $payAmts[]  = $downInst;

                // Monthly payments up to now
                $monthsPaid = min($monthsCount, now()->diffInMonths($startInst) + 1);
                for ($m = 1; $m < $monthsPaid; $m++) {
                    $due    = $startInst->copy()->addMonthsNoOverflow($m)->setDay($dayInst);
                    $offset = $faker->boolean(70) ? 0 : $faker->numberBetween(5, 10);
                    $paid   = $due->copy()->addDays($offset)->min(now());
                    $payDates[] = $paid->format('Y-m-d');
                    $payAmts[]  = $monthlyInst;
                }

                $paidSum    = array_sum($payAmts);
                $remInst    = max(0, $totalFinance - ($paidSum - $downInst));
                $isComplete = ($paidSum - $downInst) >= $totalFinance;
                $nextInst   = null;
                if (! $isComplete) {
                    $nextIdx = count($payAmts);
                    $nextDue = $startInst->copy()
                        ->addMonthsNoOverflow($nextIdx)
                        ->setDay($dayInst);
                    $nextInst = $nextDue->format('Y-m-d');
                }

                $saleInst = Sale::create([
                    'client_id'             => $client->id,
                    'sale_type'             => 'installment',
                    'total_price'           => $subtotal,
                    'discount'              => $discountInst,
                    'interest_rate'         => $rateInst,
                    'interest_amount'       => $interestInst,
                    'final_price'           => $finalInstSubtotal,
                    'down_payment'          => $downInst,
                    'monthly_installment'   => $monthlyInst,
                    'remaining_amount'      => $remInst,
                    'months_count'          => $monthsCount,
                    'payment_dates'         => $payDates,
                    'payment_amounts'       => $payAmts,
                    'status'                => $isComplete ? 'completed' : 'ongoing',
                    'notes'                 => null,
                    'preferred_payment_day' => $dayInst,
                    'next_payment_date'     => $nextInst,
                    'created_at'            => $startInst->format('Y-m-d'),
                ]);

                SaleItem::create([
                    'sale_id'    => $saleInst->id,
                    'product_id' => $product->id,
                    'quantity'   => $quantity,
                    'unit_price' => $cashPrice,
                ]);
            }
        }

        $this->command->info("✅ Seeded both cash and installment sales for 10 clients over {$monthsBack} months.");
    }
}
