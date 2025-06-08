<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MonthlySalesChart extends BaseWidget
{
    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
       $now = Carbon::now();

return [
    $this->buildStat(
        __('This Month Profit'),
        $now->copy()->startOfMonth(),
        $now->copy()->endOfMonth(),
        $now->copy()->subMonth()->startOfMonth(),
        $now->copy()->subMonth()->endOfMonth(),
        'month'
    ),
    $this->buildStat(
        __('This Week Profit'),
        $now->copy()->startOfWeek(Carbon::SATURDAY),
        $now->copy()->endOfWeek(Carbon::FRIDAY),
        $now->copy()->subWeek()->startOfWeek(Carbon::SATURDAY),
        $now->copy()->subWeek()->endOfWeek(Carbon::FRIDAY),
        'week'
    ),
    $this->buildStat(
        __('This Year Profit'),
        $now->copy()->startOfYear(),
        $now->copy()->endOfYear(),
        $now->copy()->subYear()->startOfYear(),
        $now->copy()->subYear()->endOfYear(),
        'year'
    ),
];

    }

    protected function buildStat(
        string $label,
        Carbon $currentStart,
        Carbon $currentEnd,
        Carbon $previousStart,
        Carbon $previousEnd,
        string $period
    ): Stat {
        $currentProfit = $this->calculateProfit($currentStart, $currentEnd);
        $previousProfit = $this->calculateProfit($previousStart, $previousEnd);
        
        $change = $previousProfit != 0 
            ? (($currentProfit - $previousProfit) / $previousProfit) * 100 
            : ($currentProfit != 0 ? 100 : 0);

        return Stat::make($label, number_format($currentProfit, 2) . ' ' . __('Currency'))
            ->description($this->getChangeDescription($change))
            ->descriptionIcon($change >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
            ->color($change >= 0 ? 'success' : 'danger')
            ->chart($this->getChartData($currentStart, $currentEnd, $period))
            ->chartColor($change >= 0 ? 'success' : 'danger');
    }

    protected function calculateProfit(Carbon $start, Carbon $end): float
    {
        return Sale::query()
            ->where('sale_type', 'cash')
            ->whereBetween('created_at', [$start, $end])
            ->with('items.product')
            ->get()
            ->reduce(function ($carry, $sale) {
                $cost = $sale->items->sum(fn($item) => $item->quantity * $item->product->purchase_price);
                return $carry + ($sale->final_price - $cost);
            }, 0);
    }

     protected function getChangeDescription(float $change): string
    {
        return $change >= 0
            ? __('Increased by :change%', ['change' => number_format(abs($change), 2)])
            : __('Decreased by :change%', ['change' => number_format(abs($change), 2)]);
    }

    protected function getChartData(Carbon $start, Carbon $end, string $period): array
{
    // Use Y-m-d internally for unique keys
    $labels = [];
    $data = [];
    $dateFormatLabel = match ($period) {
        'month' => 'd',  // day of month
        'week' => 'D',   // short day name e.g. Mon
        'year' => 'M',   // short month name e.g. Jan
        default => 'd',
    };

    $current = $start->copy();

    while ($current <= $end) {
        $dateKey = $current->format('Y-m-d');  // unique key
        $labels[$dateKey] = $current->format($dateFormatLabel);
        $data[$dateKey] = 0;
        $current->addDay();
    }

    Sale::query()
        ->where('sale_type', 'cash')
        ->whereBetween('created_at', [$start, $end])
        ->with('items.product')
        ->get()
        ->each(function ($sale) use (&$data) {
            $dateKey = $sale->created_at->format('Y-m-d');
            $cost = $sale->items->sum(fn($item) => $item->quantity * $item->product->purchase_price);
            $profit = $sale->final_price - $cost;
            if (isset($data[$dateKey])) {
                $data[$dateKey] += $profit;
            }
        });

    // Return both labels and values
    return [
        'labels' => array_values($labels),
        'datasets' => [
            [
                'label' => __('Profit'),
                'data' => array_values($data),
            ],
        ],
    ];
}

}