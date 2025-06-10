<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Faker\Factory as Faker;
use App\Models\Category;
use App\Models\Provider;
use App\Models\ProviderPayment;
use App\Models\Product;
use App\Models\Client;
use App\Models\ClientGuarantor;
use App\Models\Sale;
use App\Models\SaleItem;

class FullFakeDataSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();

        // Disable FK checks and truncate tables
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        SaleItem::truncate();
        Sale::truncate();
        ClientGuarantor::truncate();
        Client::truncate();
        Product::truncate();
        ProviderPayment::truncate();
        Provider::truncate();
        Category::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 1) Categories
        $categories = Category::factory(10)->create();

        // 2) Providers + payment history
        $providers = Provider::factory(5)->create();
        foreach ($providers as $provider) {
            ProviderPayment::factory(rand(5, 15))
                ->create([
                    'provider_id' => $provider->id,
                ]);
        }

        // 3) Products
        $products = Product::factory(50)->create();

        // 4) Clients + guarantors
        $clients = Client::factory(120)->create();
        foreach ($clients as $client) {
            // 1-2 guarantors each
            ClientGuarantor::factory(rand(1,2))
                ->create(['client_id' => $client->id]);
        }

        // 5) Sales & SaleItems: last year, for each month 50 cash + 30 installment
        $start = Carbon::now()->subYear()->startOfMonth();
        $productIds = $products->pluck('id')->all();

        for ($m = 0; $m < 12; $m++) {
            $monthStart = $start->copy()->addMonths($m);
            $monthEnd   = $monthStart->copy()->endOfMonth();

            // 50 cash sales
            for ($i = 0; $i < 50; $i++) {
                $dt   = $faker->dateTimeBetween($monthStart, $monthEnd);
                $date = $dt->format('Y-m-d');
                $client = $clients->random();
                $productId = $faker->randomElement($productIds);
                $price = $products->where('id', $productId)->first()->cash_price;

                $sale = Sale::create([
                    'client_id'            => $client->id,
                    'sale_type'            => 'cash',
                    'total_price'          => $price,
                    'discount'             => 0.0,
                    'interest_rate'        => 0.0,
                    'interest_amount'      => 0.0,
                    'final_price'          => $price,
                    'down_payment'         => $price,
                    'monthly_installment'  => $price,
                    'remaining_amount'     => 0.0,
                    'months_count'         => 1,
                    'payment_dates'        => [$date],
                    'payment_amounts'      => [$price],
                    'status'               => 'completed',
                    'preferred_payment_day'=> Carbon::parse($date)->day,
                    'next_payment_date'    => null,
                    'created_at'           => $date,
                    'updated_at'           => $date,
                ]);

                SaleItem::create([
                    'sale_id'    => $sale->id,
                    'product_id' => $productId,
                    'quantity'   => 1,
                    'unit_price' => $price,
                ]);
            }

            // 30 installment sales
            for ($i = 0; $i < 30; $i++) {
                $dt   = $faker->dateTimeBetween($monthStart, $monthEnd);
                $date = Carbon::instance($dt);
                $client = $clients->random();
                $productId = $faker->randomElement($productIds);
                $purchase = $products->where('id',$productId)->first()->purchase_price;
                $cashPrice = $products->where('id',$productId)->first()->cash_price;
                $quantity = 1;
                $subtotal = $cashPrice * $quantity;

                // finance calculations
                $discount = 0;
                $finalPrice = $subtotal - $discount;
                $downPayment = round($finalPrice * 0.2, 2);
                $monthsCount = $faker->numberBetween(3,12);
                $interestRate = $faker->randomFloat(2,5,15);
                $interestAmount = round(($finalPrice - $downPayment) * $interestRate/100, 2);
                $totalToPay = ($finalPrice - $downPayment) + $interestAmount;
                $monthlyInstall = round($totalToPay / $monthsCount, 2);
                $prefDay = $faker->numberBetween(1,28);

                // schedule & actual payments
                $sched = [];
                $pays  = [];
                for ($j=0; $j<$monthsCount; $j++) {
                    $dueDate = $date->copy()->addMonthsNoOverflow($j)->setDay(min($prefDay, $date->copy()->addMonthsNoOverflow($j)->daysInMonth));
                    $sched[] = $dueDate;
                    if ($dueDate->lte(Carbon::now())) {
                        $delay = $faker->boolean(50) ? 0 : $faker->numberBetween(5,10);
                        $paidDate = $dueDate->copy()->addDays($delay);
                        if ($paidDate->gt(Carbon::now())) {
                            $paidDate = Carbon::now();
                        }
                        $pays[] = ['date'=>$paidDate->format('Y-m-d'),'amount'=>$monthlyInstall];
                    }
                }
                // build arrays
                $payDates = array_column($pays,'date');
                $payAmts  = array_column($pays,'amount');
                $paidSum  = array_sum($payAmts) + $downPayment;
                $remAmt   = max(0, $totalToPay - $paidSum + $downPayment);
                $status   = (count($pays) >= $monthsCount) ? 'completed':'ongoing';
                $nextDate = null;
                if ($status==='ongoing') {
                    $nextIdx = count($pays);
                    $nd = $date->copy()->addMonthsNoOverflow($nextIdx)->setDay(min($prefDay, $date->copy()->addMonthsNoOverflow($nextIdx)->daysInMonth));
                    $nextDate = $nd->format('Y-m-d');
                }

                $sale = Sale::create([
                    'client_id'             => $client->id,
                    'sale_type'             => 'installment',
                    'total_price'           => $subtotal,
                    'discount'              => $discount,
                    'interest_rate'         => $interestRate,
                    'interest_amount'       => $interestAmount,
                    'final_price'           => $finalPrice,
                    'down_payment'          => $downPayment,
                    'monthly_installment'   => $monthlyInstall,
                    'remaining_amount'      => $remAmt,
                    'months_count'          => $monthsCount,
                    'payment_dates'         => $payDates,
                    'payment_amounts'       => $payAmts,
                    'status'                => $status,
                    'preferred_payment_day' => $prefDay,
                    'next_payment_date'     => $nextDate,
                    'created_at'            => $date->format('Y-m-d'),
                    'updated_at'            => $date->format('Y-m-d'),
                ]);

                SaleItem::create([
                    'sale_id'    => $sale->id,
                    'product_id' => $productId,
                    'quantity'   => $quantity,
                    'unit_price' => $cashPrice,
                ]);
            }
        }
    }
}
