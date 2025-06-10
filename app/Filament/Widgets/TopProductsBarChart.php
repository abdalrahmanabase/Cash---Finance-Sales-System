<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Product;

class TopProductsBarChart extends ChartWidget
{
    protected static ?string $heading = null;

    public function getHeading(): string
    {
        return __('Top 5 Products by Stock');
    }

    protected function getData(): array
    {
        $products = Product::orderByDesc('stock')->take(5)->get();

        return [
            'datasets' => [
                [
                    'label' => __('Stock'),
                    'data' => $products->pluck('stock'),
                    'backgroundColor' => [
                        '#3b82f6', '#10b981', '#f59e42', '#ef4444', '#a78bfa',
                    ],
                ],
            ],
            'labels' => $products->pluck('name'),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}