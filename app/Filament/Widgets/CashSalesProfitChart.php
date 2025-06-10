<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class CashSalesProfitChart extends ChartWidget
{
    protected static ?string $heading = null;
    protected static ?string $maxHeight = '300px';
    protected static ?string $pollingInterval = null;

    public function getHeading(): string
    {
        return __('Sales Profit Trend');
    }

    public ?string $filter = 'this_month';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getFilters(): ?array
    {
        return [
            'today' => __('Today'),
            'this_week' => __('This Week'),
            'this_month' => __('This Month'),
            'last_month' => __('Last Month'),
            'this_year' => __('This Year'),
        ];
    }

    protected function getData(): array
    {
        $now = Carbon::now();
        $filter = $this->filter;

        [$start, $end, $format, $intervalMethod] = match ($filter) {
            'today' => [
                $now->copy()->startOfDay(),
                $now->copy()->endOfDay(),
                'H:00',
                'addHour',
            ],
            'this_week' => [
                $now->copy()->startOfWeek(Carbon::SATURDAY),
                $now->copy()->endOfWeek(Carbon::FRIDAY),
                'D',
                'addDay',
            ],
            'last_month' => [
                $now->copy()->subMonth()->startOfMonth(),
                $now->copy()->subMonth()->endOfMonth(),
                'M d',
                'addDay',
            ],
            'this_year' => [
                $now->copy()->startOfYear(),
                $now->copy()->endOfYear(),
                'M',
                'addMonth',
            ],
            default => [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
                'M d',
                'addDay',
            ],
        };

        $labels = [];
        $data = [];
        $current = $start->copy();

        while ($current <= $end) {
            $label = $current->format($format);
            $labels[] = $label;
            $data[$label] = 0;
            $current->{$intervalMethod}();
        }

        Sale::query()
            ->where('sale_type', 'cash')
            ->whereBetween('created_at', [$start, $end])
            ->with('items.product')
            ->get()
            ->each(function ($sale) use (&$data, $format, $filter) {
                $timestamp = match ($filter) {
                    'today' => $sale->created_at->copy()->startOfHour(),
                    default => $sale->created_at,
                };

                $label = $timestamp->format($format);
                $cost = $sale->items->sum(fn($item) => $item->quantity * $item->product->purchase_price);
                $profit = $sale->final_price - $cost;

                if (isset($data[$label])) {
                    $data[$label] += $profit;
                }
            });

        return [
            'labels' => array_keys($data),
            'datasets' => [
                [
                    'label' => __('Profit (جم)'),
                    'data' => array_values($data),
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.2)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
        ];
    }
}
