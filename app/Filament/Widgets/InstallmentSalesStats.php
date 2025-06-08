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
            $actualDue = $sale->getPaymentScheduleProgress()['next_payment_due'];

            $expectedPayment += $actualDue;
            $expectedProfit  += $sale->getProfitOnDueAmount();
            $expectedCapital += $sale->getCapitalOnDueAmount();

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
                    if ($paidThisMonthAmount < $actualDue) {
                        $remainingThisMonth += max(0, $actualDue - $paidThisMonthAmount);
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
            Stat::make(__('Expected Payment'), number_format($expectedPayment, 2) . ' جم')
                ->description(__('Expected profit: :profit جم | Capital: :capital جم', ['profit' => number_format($expectedProfit, 2), 'capital' => number_format($expectedCapital, 2)]))
                ->color('primary'),

            Stat::make(__('Paid'), number_format($paidThisMonth, 2) . ' جم')
                ->description(__('Profit: :profit جم | Capital: :capital جم', ['profit' => number_format($paidProfit, 2), 'capital' => number_format($paidCapital, 2)]))
                ->color('success'),

            Stat::make(__('Remaining This Month'), number_format($remainingThisMonth, 2) . ' جم')
                ->description(__('Still due for selected period'))
                ->color('warning'),

            Stat::make(__('Previous Months Unpaid'), number_format($remainingFromPrevious, 2) . ' جم')
                ->description(__('Unpaid from earlier months'))
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
