<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Client;
use App\Models\Product;
use App\Models\Provider;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Faker\Factory as FakerFactory;

class FakeInstallmentSalesSeeder extends Seeder
{
    public function run(): void
    {
        $faker = FakerFactory::create();

        // 1. Truncate (reset) and make sure categories/providers exist.
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');
        SaleItem::truncate();
        Sale::truncate();
        Product::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');

        $category = Category::first() ?: Category::factory()->create();
        $provider = Provider::first() ?: Provider::factory()->create();

        // 2. Create 10 fixed products
        $products = collect();
        for ($i = 1; $i <= 10; $i++) {
            $products->push(Product::create([
                'name'           => 'Product ' . $i,
                'code'           => 'P' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'purchase_price' => 1000,
                'cash_price'     => 3000,
                'profit'         => 2000,
                'stock'          => 50,
                'is_active'      => true,
                'category_id'    => $category->id,
                'provider_id'    => $provider->id,
            ]));
        }

        $products = Product::all()->take(10);

        // 3. Make sure at least 10 clients
        $allClients = Client::all();
        if ($allClients->count() < 10) {
            $this->command->warn('You need at least 10 clients.');
            return;
        }
        $clients = $allClients->take(10);

        $discountOptions = [100, 200, 300, 400, 500];

        // 4. Seed 6 months: for each month, for each client, assign a different product.
        $monthsBack = 6;
        for ($i = 0; $i < $monthsBack; $i++) {
            $saleMonth = now()->copy()->subMonths($i)->startOfMonth();
            foreach ($clients as $clientIndex => $client) {
                $product = $products[$clientIndex % 10];

                $quantity = 1;
                $purchase_price = $product->purchase_price;
                $cash_price = $product->cash_price;
                $subtotal = $quantity * $cash_price;

                $discount = $discountOptions[$clientIndex % count($discountOptions)];
                $final_price = $subtotal - $discount;
                $down_payment = round($final_price * 0.3);
                $interest_rate = 40;
                $remaining_principal = $final_price - $down_payment;
                $interest_amount = round($remaining_principal * $interest_rate / 100);
                $total_to_pay = $remaining_principal + $interest_amount;
                $months_count = 12;
                $monthly_installment = round($total_to_pay / $months_count, 2);
                $preferred_day = rand(5, 25);

                // ---- Simulate payments (add down payment + monthly up to this month) ----
                $payment_dates = [];
                $payment_amounts = [];

                // Down payment (on sale creation date)
                $downPayDate = $saleMonth->copy()->setDay($preferred_day)->format('Y-m-d');
                $payment_dates[] = $downPayDate;
                $payment_amounts[] = $down_payment;

                // How many months paid so far (up to now, but not more than $months_count)
                $monthsPaid = min($months_count, now()->diffInMonths($saleMonth) + 1);

                for ($m = 0; $m < $monthsPaid; $m++) {
                    $payDate = $saleMonth->copy()->addMonthsNoOverflow($m)->setDay($preferred_day);
                    if ($payDate->isFuture()) break;
                    // Payments start *after* down payment
                    if ($m > 0) {
                        $payment_dates[] = $payDate->format('Y-m-d');
                        $payment_amounts[] = $monthly_installment;
                    }
                }

                $paidAmount = array_sum($payment_amounts);
                $remainingAmount = max(0, $total_to_pay - ($paidAmount - $down_payment));

                // Status & next payment date
                if (($paidAmount - $down_payment) >= $total_to_pay) {
                    $status = 'completed';
                    $next_payment_date = null;
                } else {
                    $status = 'ongoing';
                    $nextMonthIndex = count($payment_amounts); // how many months paid
                    $nextPayDay = min($preferred_day, $saleMonth->copy()->addMonthsNoOverflow($nextMonthIndex)->daysInMonth);
                    $next_payment_date = $saleMonth->copy()
                        ->addMonthsNoOverflow($nextMonthIndex)
                        ->setDay($nextPayDay)
                        ->format('Y-m-d');
                }

                // ---- Create Sale & SaleItem ----
                $sale = Sale::create([
                    'client_id' => $client->id,
                    'sale_type' => 'installment',
                    'total_price' => $subtotal,
                    'discount' => $discount,
                    'interest_rate' => $interest_rate,
                    'interest_amount' => $interest_amount,
                    'final_price' => $final_price,
                    'down_payment' => $down_payment,
                    'monthly_installment' => $monthly_installment,
                    'remaining_amount' => $remainingAmount,
                    'months_count' => $months_count,
                    'payment_dates' => $payment_dates,
                    'payment_amounts' => $payment_amounts,
                    'status' => $status,
                    'preferred_payment_day' => $preferred_day,
                    'next_payment_date' => $next_payment_date,
                    'created_at' => $saleMonth,
                    'updated_at' => now(),
                ]);

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $cash_price,
                ]);
            }
        }

        $this->command->info('✅ Seeded 6 months × 10 clients = 60 installment sales.');
    }
}
