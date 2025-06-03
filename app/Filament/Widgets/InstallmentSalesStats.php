<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Filament\Pages\InstallmentSalesSummary;

class InstallmentSalesStats extends BaseWidget
{
    public ?string $selectedMonth = null;

    protected static bool $refreshOnWidgetDataChanges = true;

    protected function getStats(): array
    {
        $selectedMonth = $this->selectedMonth ?? now()->format('Y-m');
        $isAll = $selectedMonth === 'all' || $selectedMonth === null;

        $filteredSales = $this->getFilteredSales();

        $expectedPayment    = 0;
        $expectedProfit     = 0;
        $expectedCapital    = 0;
        $paidThisMonth      = 0;
        $paidProfit         = 0;
        $paidCapital        = 0;
        $remainingThisMonth = 0;

        foreach ($filteredSales as $sale) {
            $monthsCount    = max($sale->months_count, 1);
            $monthlyCapital = $sale->total_cost / $monthsCount;

            $monthlyProfit = (
                $sale->final_price - $sale->down_payment - $sale->total_cost + ($sale->interest_amount ?? 0)
            ) / $monthsCount;

            $monthlyPayment = $sale->monthly_installment;

            if ($sale->remaining_months == 1 && $sale->remaining_amount > 0 && $sale->remaining_amount < $monthlyPayment) {
                $monthlyPayment = $sale->remaining_amount;
            }

            $expectedPayment += $monthlyPayment;
            $expectedProfit  += $monthlyProfit;
            $expectedCapital += $monthlyCapital;

            $grouped = $sale->getPaymentsGroupedByMonth();

            if ($isAll) {
                foreach ($grouped as $month) {
                    $paidThisMonth += $month['amount'];
                    $paidProfit    += $month['profit'];
                    $paidCapital   += $month['capital'];
                }
            } else {
                if (isset($grouped[$selectedMonth])) {
                    $paid = $grouped[$selectedMonth]['amount'];
                    $paidThisMonth += $paid;
                    $paidProfit  += $grouped[$selectedMonth]['profit'];
                    $paidCapital += $grouped[$selectedMonth]['capital'];
                }

                $scheduledDate = $sale->getScheduledPaymentDateForMonth($selectedMonth);
                if ($scheduledDate) {
                    $paidThisMonthAmount = $grouped[$selectedMonth]['amount'] ?? 0;
                    if ($paidThisMonthAmount < $monthlyPayment) {
                        $remainingThisMonth += max(0, $monthlyPayment - $paidThisMonthAmount);
                    }
                }
            }
        }

        // Previous months unpaid (always use full data)
        $allSales = Sale::where('sale_type', 'installment')->get();
        $remainingFromPrevious = $allSales->filter(function ($sale) use ($selectedMonth, $isAll) {
            if ($sale->status !== 'ongoing' || $sale->next_payment_date === 'Ended') {
                return false;
            }

            $next = $sale->next_payment_date;
            try {
                $due = Carbon::createFromFormat('d-m-Y', $next);
            } catch (\Exception $e) {
                return false;
            }

            return !$isAll && $due->format('Y-m') < $selectedMonth;
        })->sum('remaining_amount');

        return [
            Stat::make('Expected Payment', number_format($expectedPayment, 2) . ' EGP')
                ->description('Expected profit: ' . number_format($expectedProfit, 2) . ' EGP | Capital: ' . number_format($expectedCapital, 2) . ' EGP')
                ->color('primary'),

            Stat::make('Paid', number_format($paidThisMonth, 2) . ' EGP')
                ->description('Profit: ' . number_format($paidProfit, 2) . ' EGP | Capital: ' . number_format($paidCapital, 2) . ' EGP')
                ->color('success'),

            Stat::make('Remaining This Month', number_format($remainingThisMonth, 2) . ' EGP')
                ->description('Still due for selected period')
                ->color('warning'),

            Stat::make('Previous Months Unpaid', number_format($remainingFromPrevious, 2) . ' EGP')
                ->description('Unpaid from earlier months')
                ->color('danger'),
        ];
    }

    protected function getFilteredSales(): Collection
    {
        $selectedMonth = $this->selectedMonth ?? now()->format('Y-m');
        $query = Sale::with('items.product')->where('sale_type', 'installment');

        return InstallmentSalesSummary::filterSalesByMonth($query, $selectedMonth)->get();
    }
}
