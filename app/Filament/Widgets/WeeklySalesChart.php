<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Sale;
use Illuminate\Support\Carbon;

class WeeklySalesChart extends ChartWidget
{
    protected static ?string $heading = null;

    public function getHeading(): string
    {
        return __('Weekly Sales');
    }
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $startOfWeek = now()->startOfWeek();
        $sales = Sale::where('sale_type', 'cash')
            ->whereBetween('created_at', [$startOfWeek, now()])
            ->get()
            ->groupBy(fn ($sale) => Carbon::parse($sale->created_at)->format('l'))
            ->map(fn ($group) => $group->sum('final_price'));


        $labels = collect(Carbon::getDays())->map(fn ($day) => $day);
        $data = $labels->map(fn ($day) => $sales[$day] ?? 0);

        return [
            'datasets' => [
                [
                    'label' => __('Sales'),
                    'data' => $data->toArray(),
                ],
            ],
            'labels' => $labels->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}

