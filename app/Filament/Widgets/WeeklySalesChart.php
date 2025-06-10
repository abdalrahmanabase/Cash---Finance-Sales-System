<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\Sale;
use Illuminate\Support\Carbon;

class WeeklySalesChart extends ChartWidget
{
    protected static ?string $heading = null;
protected static bool $isDiscovered = false;
    public function getHeading(): string
    {
        return __('Weekly Sales');
    }

    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $startOfWeek = now()->startOfWeek(Carbon::SATURDAY);
        $endOfWeek = now()->endOfWeek(Carbon::FRIDAY);

        $sales = Sale::where('sale_type', 'cash')
            ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
            ->get()
            ->groupBy(fn ($sale) => Carbon::parse($sale->created_at)->translatedFormat('l'))
            ->map(fn ($group) => $group->sum('final_price'));

        $labels = collect(Carbon::getDays())->map(fn ($day) => Carbon::createFromFormat('l', $day)->translatedFormat('l'));
        $data = $labels->map(fn ($day) => $sales[$day] ?? 0);

        return [
            'datasets' => [
                [
                    'label' => __('Sales'),
                    'data' => $data->toArray(),
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.2)',
                    'fill' => true,
                    'tension' => 0.4,
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

