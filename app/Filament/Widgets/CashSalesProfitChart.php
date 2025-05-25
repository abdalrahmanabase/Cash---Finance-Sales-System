<?php

namespace App\Filament\Widgets;

use App\Models\Sale;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class CashSalesProfitChart extends ChartWidget
{
    protected static ?string $heading = 'Sales Profit Trend';
    protected static ?string $maxHeight = '300px';
    protected static ?string $pollingInterval = null;

    protected function getData(): array
    {
        $now = Carbon::now();
        $start = $now->copy()->startOfMonth();
        $end = $now->copy()->endOfMonth();

        // Initialize data for all days in month
        $labels = [];
        $data = [];
        $current = $start->copy();

        while ($current <= $end) {
            $labels[] = $current->format('M d');
            $data[$current->format('Y-m-d')] = 0;
            $current->addDay();
        }

        // Get sales data
        Sale::query()
            ->where('sale_type', 'cash')
            ->whereBetween('created_at', [$start, $end])
            ->with('items.product')
            ->get()
            ->each(function ($sale) use (&$data) {
                $date = $sale->created_at->format('Y-m-d');
                $cost = $sale->items->sum(fn($item) => $item->quantity * $item->product->purchase_price);
                $profit = $sale->final_price - $cost;
                $data[$date] += $profit;
            });

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Daily Profit (EGP)',
                    'data' => array_values($data),
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.2)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}