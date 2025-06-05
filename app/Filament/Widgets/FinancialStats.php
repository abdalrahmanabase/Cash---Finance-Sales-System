<?php

namespace App\Filament\Widgets;

use App\Models\Expense;
use App\Models\Sale;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Card;

class FinancialStats extends StatsOverviewWidget
{
    public string $period = 'all_time';
    public array $explainRows = [];
    public string $periodLabel = '';

    protected function getCards(): array
    {
        $now       = Carbon::now();
        $periodKey = $this->period ?? 'all_time';

        if ($periodKey === 'all_time') {
            $label       = 'All Time';
            $filterFn    = fn($d) => true;
            $periodStart = null;
            $periodEnd   = null;
        } elseif ($periodKey === 'this_year') {
            $label       = $now->year;
            $filterFn    = fn($d) => Carbon::parse($d)->year === $now->year;
            $periodStart = Carbon::create($now->year, 1, 1)->startOfDay();
            $periodEnd   = Carbon::create($now->year, 12, 31)->endOfDay();
        } elseif (preg_match('/^(\d{4})-(\d{2})$/', $periodKey, $m)) {
            $year        = (int)$m[1];
            $month       = (int)$m[2];
            $label       = Carbon::create($year, $month)->format('F Y');
            $filterFn    = fn ($d) => (Carbon::parse($d)->year === $year && Carbon::parse($d)->month === $month);
            $periodStart = Carbon::create($year, $month, 1)->startOfDay();
            $periodEnd   = Carbon::create($year, $month, 1)->endOfMonth()->endOfDay();
        } else {
            $label       = 'All Time';
            $filterFn    = fn($d) => true;
            $periodStart = null;
            $periodEnd   = null;
        }
        $this->periodLabel = $label;

        $allSales = Sale::with(['items.product', 'client'])->get();

        $totalRevenue = 0;
        $totalCapital = 0;
        $totalProfit  = 0;
        $rows = [];

        foreach ($allSales as $sale) {
            $purchaseCost = (float) $sale->items->sum(fn($item) => ($item->quantity ?? 0) * ($item->product->purchase_price ?? 0));
            $finalPrice   = (float) $sale->final_price;
            $interest     = (float) ($sale->interest_amount ?? 0);
            $downPayment  = (float) ($sale->down_payment ?? 0);
            $months       = (int) ($sale->months_count ?? 1);

            // CASH SALES: Recognize immediately if in period
            if ($sale->sale_type === 'cash') {
                if ($filterFn($sale->created_at)) {
                    $capital = $purchaseCost;
                    $profit = ($finalPrice + $interest) - $purchaseCost;
                    $revenue = $finalPrice;

                    $rows[] = [
                        'sale_id'         => $sale->id,
                        'client'          => optional($sale->client)->name ?? '—',
                        'date'            => $sale->created_at->format('Y-m-d'),
                        'type'            => 'Cash',
                        'amount_paid'     => $finalPrice,
                        'capital'         => (int) round($capital),
                        'profit'          => (int) round($profit),
                    ];
                    $totalCapital += $capital;
                    $totalProfit += $profit;
                    $totalRevenue += $revenue;
                }
            }

            // INSTALLMENT SALES: Recognize down payment, and then split each payment
            if ($sale->sale_type === 'installment') {
                // 1. Down payment (always capital)
                if ($downPayment > 0) {
                    $downDate = $sale->created_at->format('Y-m-d');
                    $inPeriod = $filterFn($downDate);
                    if ($inPeriod) {
                        $rows[] = [
                            'sale_id'     => $sale->id,
                            'client'      => optional($sale->client)->name ?? '—',
                            'date'        => $downDate,
                            'type'        => 'Down Payment',
                            'amount_paid' => (int) round($downPayment),
                            'capital'     => (int) round($downPayment),
                            'profit'      => 0,
                        ];
                        $totalCapital += $downPayment;
                        $totalRevenue += $downPayment;
                    }
                }

                // 2. The rest of the capital (after down payment)
                $remainingCapital = max(0, $purchaseCost - $downPayment);
                $totalProfitOnSale = ($finalPrice + $interest) - $purchaseCost;
                $monthlyCapital = $months > 0 ? $remainingCapital / $months : 0;
                $monthlyProfit = $months > 0 ? $totalProfitOnSale / $months : 0;

                // 3. Go through each actual payment, split proportionally
                $dates   = $sale->payment_dates   ?? [];
                $amounts = $sale->payment_amounts ?? [];
                foreach ($dates as $i => $d) {
                    $amt = (float) ($amounts[$i] ?? 0);
                    if ($amt <= 0) continue;
                    $payDate = Carbon::parse($d);
                    $inPeriod = $filterFn($d);
                    if ($inPeriod) {
                        // Split by portion of full month
                        $capitalPart = 0;
                        $profitPart = 0;

                        if ($monthlyCapital + $monthlyProfit > 0) {
                            $portion = $amt / ($monthlyCapital + $monthlyProfit);
                            $capitalPart = $monthlyCapital * $portion;
                            $profitPart  = $monthlyProfit  * $portion;
                        }

                        $rows[] = [
                            'sale_id'     => $sale->id,
                            'client'      => optional($sale->client)->name ?? '—',
                            'date'        => $payDate->format('Y-m-d'),
                            'type'        => 'Installment',
                            'amount_paid' => (int) round($amt),
                            'capital'     => (int) round($capitalPart),
                            'profit'      => (int) round($profitPart),
                        ];
                        $totalCapital += $capitalPart;
                        $totalProfit  += $profitPart;
                        $totalRevenue += $amt;
                    }
                }
            }
        }

        // Expenses
        $expensesInPeriod = Expense::all()->filter(fn($e) => $filterFn($e->date));
        $totalExpenses    = (int) round($expensesInPeriod->sum('amount'));
        $netProfit = (int) round($totalProfit - $totalExpenses);

        $this->explainRows = $rows;

        return [
            Card::make('Revenue', number_format($totalRevenue, 0) . ' EGP')
                ->description("All cash and actually paid installments for {$label}")
                ->color('success'),
            Card::make('Capital', number_format($totalCapital, 0) . ' EGP')
                ->description("Capital portion of payments for {$label}")
                ->color('danger'),
            Card::make('Profit', number_format($totalProfit, 0) . ' EGP')
                ->description("Profit portion of paid amounts for {$label}")
                ->color('primary'),
            Card::make('Expenses', number_format($totalExpenses, 0) . ' EGP')
                ->description("Expenses for {$label}")
                ->color('warning'),
            Card::make('Net Profit', number_format($netProfit, 0) . ' EGP')
                ->description("Profit − Expenses for {$label}")
                ->color('success'),
        ];
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        $this->getCards();

        return view('filament.widgets.financial-stats', [
            'explainRows' => $this->explainRows,
            'periodLabel' => $this->periodLabel,
        ]);
    }
}
