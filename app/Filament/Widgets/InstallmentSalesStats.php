<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InstallmentSalesStats extends BaseWidget
{
    protected static bool $refreshOnWidgetDataChanges = true;
    protected static bool $isDiscovered = false;

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

        $sales = Sale::activeInstallments()->get();

        $paidThisMonth = $paidProfit = $paidCapital = 0;
        $remainingThisMonth = $remainingProfit = $remainingCapital = 0;
        $previousMonthsUnpaid = $prevUnpaidProfit = $prevUnpaidCapital = 0;

        foreach ($sales as $sale) {
            foreach ($sale->getPaymentsForMonth($currentMonthYear) as $payment) {
                $paidThisMonth += $payment['amount'];
                if ($sale->final_price > 0) {
                    $paidProfit += ($payment['amount'] / $sale->final_price) * $sale->profit;
                    $paidCapital += ($payment['amount'] / $sale->final_price) * $sale->total_cost;
                }
            }

            $nextPaymentDate = $sale->next_payment_date;
            if (!$nextPaymentDate) continue;

            $dueAmount = $sale->current_month_due;

            if ($nextPaymentDate->format('Y-m') === $currentMonthYear) {
                $remainingThisMonth += $dueAmount;
                $remainingProfit += $sale->getProfitOnDueAmount();
                $remainingCapital += $sale->getCapitalOnDueAmount();
            } elseif ($nextPaymentDate->lt($firstDayOfCurrentMonth)) {
                $monthsMissed = $firstDayOfCurrentMonth->diffInMonths($nextPaymentDate);
                $overdueAmount = ($monthsMissed * $sale->monthly_installment) + $dueAmount;
                $previousMonthsUnpaid += $overdueAmount;

                if ($sale->final_price > 0) {
                    $prevUnpaidProfit += ($overdueAmount / $sale->final_price) * $sale->profit;
                    $prevUnpaidCapital += ($overdueAmount / $sale->final_price) * $sale->total_cost;
                }
            }
        }

        return [
            Stat::make(__('Paid This Month'), $nf($paidThisMonth) . ' ' . $cs)
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