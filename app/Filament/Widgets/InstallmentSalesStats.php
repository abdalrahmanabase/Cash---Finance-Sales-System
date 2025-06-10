<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Log;

class InstallmentSalesStats extends BaseWidget
{
    protected static bool $refreshOnWidgetDataChanges = true;

    protected function getCurrencySymbol(): string
    {
        return app()->getLocale() === 'ar' ? 'جم' : 'EGP';
    }

    protected function getStats(): array
    {
        $cs = $this->getCurrencySymbol();
        $nf = fn($v) => number_format($v, 0, '', ',');

        $currentMonthYear = now()->format('Y-m');
        $firstDayOfCurrentMonth = now()->startOfMonth();

        // Use the 'activeInstallments' scope to fetch only ongoing installment sales
        $sales = Sale::activeInstallments()->get();

        // --- Initialize all totals to zero ---
        $expectedThisMonth = $paidThisMonth = $remainingThisMonth = $previousMonthsUnpaid = 0;
        $expectedProfit = $expectedCapital = 0;
        $paidProfit = $paidCapital = 0;
        $remainingProfit = $remainingCapital = 0;
        $prevUnpaidProfit = $prevUnpaidCapital = 0;

        foreach ($sales as $sale) {
            // --- 1. Calculate "Paid" Stat (Cash Flow This Month) ---
            // This sums up all payments recorded with a date in the current calendar month.
            foreach ($sale->getPaymentsForMonth($currentMonthYear) as $payment) {
                $paidThisMonth += $payment['amount'];
                // Prorate profit/capital for the paid amount, assuming consistent margin on final price
                if ($sale->final_price > 0) {
                    $paidProfit += ($payment['amount'] / $sale->final_price) * $sale->profit;
                    $paidCapital += ($payment['amount'] / $sale->final_price) * $sale->total_cost;
                }
            }

            $nextPaymentDate = $sale->next_payment_date;
            if (!$nextPaymentDate) {
                continue; // Skip if there's no next payment date for an ongoing sale
            }

            // Get the remaining amount for the current installment cycle.
            // This is used for all calculations involving current and overdue amounts.
            $dueAmount = $sale->current_month_due;

            if ($nextPaymentDate->format('Y-m') === $currentMonthYear) {
                // --- 2. Handle Sales with Dues in the CURRENT Month ---

                // ** MODIFIED LOGIC PER YOUR REQUEST **
                // "Expected Payment" is the sum of currently outstanding due amounts for this month.
                $expectedThisMonth += $dueAmount;

                // "Remaining This Month" is also the current outstanding balance for this month's installment.
                $remainingThisMonth += $dueAmount;

                // --- Associated Profit & Capital ---
                // Both Expected and Remaining profit/capital will be based on the dueAmount.
                $profitOnDue = $sale->getProfitOnDueAmount();
                $capitalOnDue = $sale->getCapitalOnDueAmount();

                $expectedProfit += $profitOnDue;
                $expectedCapital += $capitalOnDue;
                $remainingProfit += $profitOnDue;
                $remainingCapital += $capitalOnDue;

            } elseif ($nextPaymentDate->lt($firstDayOfCurrentMonth)) {
                // --- 3. Handle OVERDUE Sales from PREVIOUS Months ---

                // Calculate how many full months have been missed between the due date and now.
                $monthsMissed = $firstDayOfCurrentMonth->diffInMonths($nextPaymentDate);

                // "Previous Months Unpaid" = (all fully missed installments) + (the partial balance of the first overdue installment).
                $overdueAmount = ($monthsMissed * $sale->monthly_installment) + $dueAmount;
                $previousMonthsUnpaid += $overdueAmount;

                // --- Associated Profit & Capital for Overdue Amount ---
                if ($sale->final_price > 0) {
                    $prevUnpaidProfit += ($overdueAmount / $sale->final_price) * $sale->profit;
                    $prevUnpaidCapital += ($overdueAmount / $sale->final_price) * $sale->total_cost;
                }
            }
        }

        return [
            Stat::make(__('Expected Payment'), $nf($expectedThisMonth) . ' ' . $cs)
                ->description(__('Profit: :profit :currency | Capital: :capital :currency', [
                    'profit' => $nf($expectedProfit),
                    'capital' => $nf($expectedCapital),
                    'currency' => $cs,
                ]))
                ->color('primary'),

            Stat::make(__('Paid'), $nf($paidThisMonth) . ' ' . $cs)
                ->description(__('Profit: :profit :currency | Capital: :capital :currency', [
                    'profit' => $nf($paidProfit),
                    'capital' => $nf($paidCapital),
                    'currency' => $cs,
                ]))
                ->color('success'),

            Stat::make(__('Remaining This Month'), $nf($remainingThisMonth) . ' ' . $cs)
                ->description(__('Profit: :profit :currency | Capital: :capital :currency', [
                    'profit' => $nf($remainingProfit),
                    'capital' => $nf($remainingCapital),
                    'currency' => $cs,
                ]))
                ->color('warning'),

            Stat::make(__('Previous Months Unpaid'), $nf($previousMonthsUnpaid) . ' ' . $cs)
                ->description(__('Profit: :profit :currency | Capital: :capital :currency', [
                    'profit' => $nf($prevUnpaidProfit),
                    'capital' => $nf($prevUnpaidCapital),
                    'currency' => $cs,
                ]))
                ->color('danger'),
        ];
    }
}
