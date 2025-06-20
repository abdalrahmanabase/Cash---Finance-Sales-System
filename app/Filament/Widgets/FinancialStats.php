<?php

namespace App\Filament\Widgets;

use App\Models\Expense;
use App\Models\Sale;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Card;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
class FinancialStats extends StatsOverviewWidget
{
    public string $period = 'all_time';
    public $explainRows = [];
    public string $periodLabel = '';
    protected static bool $isDiscovered = false;

    protected function getCurrencySymbol(): string
    {
        return app()->getLocale() === 'ar' ? 'جم' : 'EGP';
    }

    protected function getCards(): array
    {
        $now = Carbon::now();
        $periodKey = $this->period ?? 'all_time';

        if ($periodKey === 'all_time') {
            $label = __('All Time');
            $filterFn = fn($d) => true;
        } elseif ($periodKey === 'this_year') {
            $label = $now->year;
            $filterFn = fn($d) => Carbon::parse($d)->year === $now->year;
        } elseif (preg_match('/^(\d{4})-(\d{2})$/', $periodKey, $m)) {
            $year = (int)$m[1];
            $month = (int)$m[2];
            $label = Carbon::create($year, $month)->translatedFormat('F Y');
            $filterFn = fn($d) => (
                Carbon::parse($d)->year === $year
                && Carbon::parse($d)->month === $month
            );
        } else {
            $label = __('All Time');
            $filterFn = fn($d) => true;
        }
        $this->periodLabel = $label;

        // ── Sales calculations ──────────────────────────────────────────────
        $allSales = Sale::with(['items.product', 'client'])->get();
        $totalRevenue = 0;
        $totalCapital = 0;
        $totalProfit = 0;
        $rows = [];

        foreach ($allSales as $sale) {
            $purchaseCost = (float) $sale->items->sum(fn($item) =>
                ($item->quantity ?? 0) * ($item->product->purchase_price ?? 0)
            );
            $finalPrice = (float) $sale->final_price;
            $interest = (float) ($sale->interest_amount ?? 0);
            $downPayment = (float) ($sale->down_payment ?? 0);
            $months = (int) ($sale->months_count ?? 1);

            // Cash sale
            if ($sale->sale_type === 'cash' && $filterFn($sale->created_at)) {
                $capital = $purchaseCost;
                $profit = ($finalPrice + $interest) - $purchaseCost;
                $revenue = $finalPrice;

                $rows[] = [
                    'sale_id' => $sale->id,
                    'client' => optional($sale->client)->name ?? '—',
                    'date' => $sale->created_at->format('Y-m-d'),
                    'type' => __('Cash'),
                    'amount_paid' => number_format($finalPrice, 0) . ' ' . $this->getCurrencySymbol(),
                    'capital' => number_format($capital, 0) . ' ' . $this->getCurrencySymbol(),
                    'profit' => number_format($profit, 0) . ' ' . $this->getCurrencySymbol(),
                ];
                $totalCapital += $capital;
                $totalProfit += $profit;
                $totalRevenue += $revenue;
            }

            // Installment sale
            if ($sale->sale_type === 'installment') {
                // Down payment
                if ($downPayment > 0) {
                    $downDate = $sale->created_at->format('Y-m-d');
                    if ($filterFn($downDate)) {
                        $rows[] = [
                            'sale_id' => $sale->id,
                            'client' => optional($sale->client)->name ?? '—',
                            'date' => $downDate,
                            'type' => __('Down Payment'),
                            'amount_paid' => (int) round($downPayment),
                            'capital' => (int) round($downPayment),
                            'profit' => 0,
                        ];
                        $totalCapital += $downPayment;
                        $totalRevenue += $downPayment;
                    }
                }

                // Remaining installments
                $remainingCapital = max(0, $purchaseCost - $downPayment);
                $totalProfitOnSale = ($finalPrice + $interest) - $purchaseCost;
                $monthlyCapital = $months > 0 ? $remainingCapital / $months : 0;
                $monthlyProfit = $months > 0 ? $totalProfitOnSale / $months : 0;

                $dates = $sale->payment_dates ?? [];
                $amounts = $sale->payment_amounts ?? [];
                foreach ($dates as $i => $d) {
                    $amt = (float) ($amounts[$i] ?? 0);
                    if ($amt <= 0) continue;

                    if ($filterFn($d)) {
                        $portion = $monthlyCapital + $monthlyProfit > 0
                            ? $amt / ($monthlyCapital + $monthlyProfit)
                            : 0;
                        $capitalPart = $monthlyCapital * $portion;
                        $profitPart = $monthlyProfit * $portion;

                        $rows[] = [
                            'sale_id' => $sale->id,
                            'client' => optional($sale->client)->name ?? '—',
                            'date' => Carbon::parse($d)->format('Y-m-d'),
                            'type' => __('Installment'),
                            'amount_paid' => (int) round($amt),
                            'capital' => (int) round($capitalPart),
                            'profit' => (int) round($profitPart),
                        ];
                        $totalCapital += $capitalPart;
                        $totalProfit += $profitPart;
                        $totalRevenue += $amt;
                    }
                }
            }
        }

        // ── Expenses calculations ───────────────────────────────────────────
        $expensesInPeriod = Expense::all()->filter(fn($e) => $filterFn($e->date));
        $totalExpenses = (int) round($expensesInPeriod->sum('amount'));
        $ownerPaidExpenses = (int) round(
            $expensesInPeriod
                ->where('type', 'Paid For Owner')
                ->sum('amount')
        );

        $netProfit = (int) round($totalProfit - $totalExpenses);
        $this->explainRows = $rows;

        return [
            Card::make(__('Revenue'), number_format($totalRevenue, 0) . ' ' . $this->getCurrencySymbol())
                ->description(__('All cash & paid installments for :label', ['label' => $label]))
                ->color('success'),

            Card::make(__('Capital'), number_format($totalCapital, 0) . ' ' . $this->getCurrencySymbol())
                ->description(__('Capital portion of payments for :label', ['label' => $label]))
                ->color('danger'),

            Card::make(__('Profit'), number_format($totalProfit, 0) . ' ' . $this->getCurrencySymbol())
                ->description(__('Profit portion of paid amounts for :label', ['label' => $label]))
                ->color('primary'),

            Card::make(__('Expenses'), number_format($totalExpenses, 0) . ' ' . $this->getCurrencySymbol())
                ->description(__('Total expenses for :label. Paid For Owner: :amount :currency', [
                    'label' => $label,
                    'amount' => number_format($ownerPaidExpenses, 0),
                    'currency' => $this->getCurrencySymbol(),
                ]))
                ->color('warning'),

            Card::make(__('Net Profit'), number_format($netProfit, 0) . ' ' . $this->getCurrencySymbol())
                ->description(__('Profit - Expenses for :label', ['label' => $label]))
                ->color('success'),
        ];
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        $this->getCards();

        $perPage = 10;
        $page = (int) request()->get('page', 1);
        $collection = collect($this->explainRows);
        $paginator = new LengthAwarePaginator(
            $collection->forPage($page, $perPage),
            $collection->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );

        $this->explainRows = $paginator;

        return view('filament.widgets.financial-stats', [
            'periodLabel' => $this->periodLabel,
        ]);
    }
}
