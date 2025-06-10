<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Filament\Pages\InstallmentSalesSummary;

class InstallmentSalesStats extends BaseWidget
{
    public ?string $selectedMonth = null;

    protected static bool $refreshOnWidgetDataChanges = true;
    protected static bool $isDiscovered = false;

    protected function getCurrencySymbol(): string
    {
        return app()->getLocale() === 'ar' ? 'جم' : 'EGP';
    }

    protected function getStats(): array
    {
        $selectedMonth = $this->selectedMonth ?? now()->format('Y-m');
        $isAll = $selectedMonth === 'all' || $selectedMonth === null;

        $expectedPayment = $expectedProfit = $expectedCapital = 0;
        $paidThisMonth = $paidProfit = $paidCapital = 0;
        $remainingThisMonth = $remainingProfit = $remainingCapital = 0;
        $previousUnpaid = $prevUnpaidProfit = $prevUnpaidCapital = 0;

        $sales = Sale::where('sale_type', 'installment')->get();

        foreach ($sales as $sale) {
            $baseDate = $sale->created_at instanceof Carbon ? $sale->created_at : Carbon::parse($sale->created_at);
            $preferredDay = (int)($sale->preferred_payment_day ?: $baseDate->day);
            $months = $sale->months_count ?? 0;
            $monthly = (float) ($sale->monthly_installment ?? 0);

            $totalCost     = $sale->total_cost;
            $downPayment   = $sale->down_payment ?? 0;
            $monthlyCapital = $months > 0 ? ($totalCost - $downPayment) / $months : 0;
            $monthlyProfit = $monthly - $monthlyCapital;

            // Schedule (skip down payment)
            $schedule = [];
            for ($i = 1; $i < $months; $i++) {
                $dueDate = $baseDate->copy()->addMonthsNoOverflow($i)->setDay(
                    min($preferredDay, $baseDate->copy()->addMonthsNoOverflow($i)->daysInMonth)
                );
                $monthKey = $dueDate->format('Y-m');
                $schedule[$monthKey][] = [
                    'date' => $dueDate->format('Y-m-d'),
                    'amount' => $monthly,
                ];
            }

            // Payments grouped by month
            $paidByMonth = [];
            $paidRows = [];
            if (is_array($sale->payment_dates) && is_array($sale->payment_amounts)) {
                foreach ($sale->payment_dates as $i => $date) {
                    $monthKey = Carbon::parse($date)->format('Y-m');
                    $amt = (float)($sale->payment_amounts[$i] ?? 0);
                    $paidByMonth[$monthKey] = ($paidByMonth[$monthKey] ?? 0) + $amt;
                    $paidRows[] = [
                        'amount' => $amt,
                        'date'   => $date,
                        'month'  => $monthKey,
                    ];
                }
            }

            // ---------- EXPECTED CARD ----------
            if (!$isAll && isset($schedule[$selectedMonth])) {
                foreach ($schedule[$selectedMonth] as $due) {
                    $expectedPayment += $monthly;
                    $expectedProfit  += $monthlyProfit;
                    $expectedCapital += $monthlyCapital;
                }
            } elseif ($isAll) {
                foreach ($schedule as $monthArr) {
                    foreach ($monthArr as $due) {
                        $expectedPayment += $monthly;
                        $expectedProfit  += $monthlyProfit;
                        $expectedCapital += $monthlyCapital;
                    }
                }
            }

            // ---------- PAID CARD ----------
            if (!$isAll) {
                foreach ($paidRows as $row) {
                    if ($row['month'] === $selectedMonth) {
                        $paidThisMonth += $row['amount'];
                        $portion = $monthly > 0 ? $row['amount'] / $monthly : 0;
                        $paidProfit  += $monthlyProfit * $portion;
                        $paidCapital += $monthlyCapital * $portion;
                    }
                }
            } else {
                foreach ($paidRows as $row) {
                    $paidThisMonth += $row['amount'];
                    $portion = $monthly > 0 ? $row['amount'] / $monthly : 0;
                    $paidProfit  += $monthlyProfit * $portion;
                    $paidCapital += $monthlyCapital * $portion;
                }
            }

            // ---------- REMAINING THIS MONTH CARD ----------
            if (!$isAll && isset($schedule[$selectedMonth])) {
                $scheduled = count($schedule[$selectedMonth]) * $monthly;
                $paid      = $paidByMonth[$selectedMonth] ?? 0;
                $rem       = $scheduled - $paid;
                if ($rem > 0) {
                    $remainingThisMonth += $rem;
                    $portion = $monthly > 0 ? $rem / $monthly : 0;
                    $remainingProfit  += $monthlyProfit * $portion;
                    $remainingCapital += $monthlyCapital * $portion;
                }
            }

            // ---------- PREVIOUS MONTHS UNPAID CARD ----------
            if (!$isAll) {
                foreach ($schedule as $monthKey => $monthArr) {
                    if ($monthKey < $selectedMonth) {
                        $scheduled = count($monthArr) * $monthly;
                        $paid      = $paidByMonth[$monthKey] ?? 0;
                        $rem       = $scheduled - $paid;
                        if ($rem > 0) {
                            $previousUnpaid += $rem;
                            $portion = $monthly > 0 ? $rem / $monthly : 0;
                            $prevUnpaidProfit  += $monthlyProfit * $portion;
                            $prevUnpaidCapital += $monthlyCapital * $portion;
                        }
                    }
                }
            }
        }

        $cs = $this->getCurrencySymbol();
        $nf = fn($v) => number_format($v, 0, '', ',');

        return [
            Stat::make(__('Expected Payment'), $nf($expectedPayment) . ' ' . $cs)
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

            Stat::make(__('Previous Months Unpaid'), $nf($previousUnpaid) . ' ' . $cs)
                ->description(__('Profit: :profit :currency | Capital: :capital :currency', [
                    'profit' => $nf($prevUnpaidProfit),
                    'capital' => $nf($prevUnpaidCapital),
                    'currency' => $cs,
                ]))
                ->color('danger'),
        ];
    }

    protected function getFilteredSales()
    {
        $selectedMonth = $this->selectedMonth ?? now()->format('Y-m');
        $query = Sale::with('items.product')->where('sale_type', 'installment');
        return InstallmentSalesSummary::filterSalesByMonth($query, $selectedMonth)->get();
    }
}
    