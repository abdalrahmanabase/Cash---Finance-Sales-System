<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Product;

class StockByCategoryDoughnutChart extends ChartWidget
{
    protected static ?string $heading = null;

    public function getHeading(): string
    {
        return __('Stock Distribution by Category');
    }

    protected function getData(): array
    {
        $categories = Product::with('category')->get()
            ->groupBy(fn($product) => $product->category->name ?? __('Uncategorized'))
            ->map(fn($group) => $group->sum('stock'));

        return [
            'datasets' => [
                [
                    'label' => __('Stock'),
                    'data' => $categories->values(),
                    'backgroundColor' => [
                        '#3b82f6', '#10b981', '#f59e42', '#ef4444', '#a78bfa', '#f472b6',
                        '#facc15', '#6366f1', '#14b8a6', '#eab308'
                    ],
                ],
            ],
            'labels' => $categories->keys(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}