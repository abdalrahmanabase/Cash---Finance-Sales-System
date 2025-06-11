<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Client;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Provide defaults for all non-nullable fields to prevent SQL errors.
        return [
            'client_id' => Client::factory(),
            'sale_type' => 'cash',
            'total_price' => 0,
            'discount' => 0,
            'interest_rate' => 0,
            'interest_amount' => 0,
            'final_price' => 0,
            'down_payment' => 0,
            'monthly_installment' => 0,
            'remaining_amount' => 0,
            'months_count' => 1,
            'status' => 'completed',
            'preferred_payment_day' => 1,
            'notes' => $this->faker->boolean(25) ? $this->faker->paragraph : null,
            // FIX: Ensure all sales are created in the past to simulate history correctly.
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'updated_at' => fn (array $attributes) => $attributes['created_at'],
        ];
    }

    /**
     * Indicate that the sale is a cash transaction.
     */
    public function cash(): static
    {
        return $this->state(['sale_type' => 'cash'])->afterCreating(function (Sale $sale) {
            $items = SaleItem::factory(rand(1, 2))->create(['sale_id' => $sale->id]);
            $totalPrice = $items->sum(fn ($item) => $item->unit_price * $item->quantity);

            $sale->updateQuietly([
                'total_price' => $totalPrice,
                'final_price' => $totalPrice,
                'down_payment' => 0,
                'monthly_installment' => 0,
                'status' => 'completed',
                'payment_dates' => [$sale->created_at->format('Y-m-d')],
                'payment_amounts' => [$totalPrice],
                'remaining_amount' => 0,
            ]);
        });
    }

    /**
     * Indicate that the sale is an installment transaction.
     */
    public function installment(): static
    {
        return $this->state(['sale_type' => 'installment'])->afterCreating(function (Sale $sale) {
            // 1. Create items and calculate base prices
            $items = SaleItem::factory(rand(1, 3))->create(['sale_id' => $sale->id]);
            $totalPrice = $items->sum(fn ($item) => $item->unit_price * $item->quantity);
            $finalPrice = $totalPrice - $sale->discount;

            // 2. Define installment parameters
            $monthsCount = $this->faker->numberBetween(3, 12);
            $interestRate = $this->faker->randomFloat(2, 5, 15);
            $preferredDay = $this->faker->numberBetween(1, 28);
            $downPayment = round($finalPrice * $this->faker->randomFloat(2, 0.1, 0.25), 2);

            // 3. Calculate financial details
            $amountToFinance = $finalPrice - $downPayment;
            $interestAmount = round($amountToFinance * ($interestRate / 100), 2);
            $totalInstallmentCost = $amountToFinance + $interestAmount;
            $monthlyInstallment = $monthsCount > 0 ? round($totalInstallmentCost / $monthsCount, 2) : 0;

            // 4. Simulate past payments
            $paymentDates = [];
            $paymentAmounts = [];
            $creationDate = Carbon::parse($sale->created_at);

            for ($i = 0; $i < $monthsCount; $i++) {
                $dueDate = $creationDate->copy()->addMonthsNoOverflow($i + 1)->setDay($preferredDay);
                if ($dueDate->isPast()) {
                    // Only create payment records for installments that were due in the past.
                    $paymentDates[] = $dueDate->format('Y-m-d');
                    $paymentAmounts[] = $monthlyInstallment;
                }
            }
            
            // 5. Calculate final status and amounts
            $totalPaid = array_sum($paymentAmounts) + $downPayment;
            $totalOwed = $finalPrice + $interestAmount;
            $remainingAmount = max(0, $totalOwed - $totalPaid);
            $isCompleted = count($paymentDates) >= $monthsCount;
            $status = $isCompleted ? 'completed' : 'ongoing';
            
            // 6. Calculate the next payment date if the sale is ongoing
            $nextPaymentDate = null;
            if ($status === 'ongoing') {
                $monthsPaid = count($paymentDates);
                $nextPaymentDate = $creationDate->copy()->addMonthsNoOverflow($monthsPaid + 1)->setDay($preferredDay)->format('Y-m-d');
            }

            // 7. Update the sale record with all calculated data
            $sale->updateQuietly([
                'total_price' => $totalPrice,
                'final_price' => $finalPrice,
                'down_payment' => $downPayment,
                'interest_rate' => $interestRate,
                'interest_amount' => $interestAmount,
                'monthly_installment' => $monthlyInstallment,
                'months_count' => $monthsCount,
                'preferred_payment_day' => $preferredDay,
                'payment_dates' => $paymentDates,
                'payment_amounts' => $paymentAmounts,
                'remaining_amount' => $remainingAmount,
                'status' => $status,
                'next_payment_date' => $nextPaymentDate,
            ]);
        });
    }
}
