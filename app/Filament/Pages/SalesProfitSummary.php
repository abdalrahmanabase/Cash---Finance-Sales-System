<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CashSalesProfitChart;
use App\Filament\Widgets\MonthlySalesChart;
use App\Models\Sale;
use Filament\Pages\Page;
use Filament\Tables;
use Carbon\Carbon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;

class SalesProfitSummary extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static string $view = 'filament.pages.sales-profit-summary';

    public static function getNavigationGroup(): ?string
    {
        return __('Sales Management');
    }

    public static function getNavigationLabel(): string
    {
        return __('Cash Sales Profit Summary');
    }

    public function getTitle(): string
    {
        return __('Cash Sales Profit Summary');
    }

    protected function getCurrencySymbol(): string
{
    return app()->getLocale() === 'ar' ? 'جم' : 'EGP';
}

    protected function getHeaderWidgets(): array
    {
        return [
            MonthlySalesChart::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            CashSalesProfitChart::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    public function getFooterWidgetsColumns(): int|array
    {
        return 1;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Date'))
                    ->date('d-m-Y')
                    ->sortable(),

                TextColumn::make('final_price')
                    ->label(__('Sale Amount'))
                    ->getStateUsing(fn ($record) => number_format($record->final_price, 2) . ' ' . $this->getCurrencySymbol())
                    ->sortable(),

                TextColumn::make('cost_raw')
                    ->label(__('Cost'))
                    ->getStateUsing(fn ($record) => number_format($record->cost_raw, 2) . ' ' . $this->getCurrencySymbol()),

                TextColumn::make('profit_raw')
                    ->label(__('Profit'))
                    ->getStateUsing(fn ($record) => number_format($record->profit_raw, 2) . ' ' . $this->getCurrencySymbol())
                    ->color(fn ($record) => $record->profit_raw >= 0 ? 'success' : 'danger'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('preset_time_range')
                    ->label(__('Quick Date Range'))
                    ->options([
                        'today' => __('Today'),
                        'yesterday' => __('Yesterday'),
                        'this_week' => __('This Week'),
                        'last_week' => __('Last Week'),
                        'this_month' => __('This Month'),
                        'last_month' => __('Last Month'),
                        'this_year' => __('This Year'),
                        'last_year' => __('Last Year'),
                    ])
                    ->default('all')
                    ->query(function (Builder $query, $state) {
                        if ($state === 'all' || empty($state)) {
                            return;
                        }

                        $this->applyPresetTimeFilter($query, $state['value']);
                    }),

                Filter::make('custom_date_range')
                    ->form([
                        DatePicker::make('start_date')
                            ->label(__('From Date')),
                        DatePicker::make('end_date')
                            ->label(__('To Date'))
                            ->default(now()),
                    ])
                    ->query(function (Builder $query, array $state) {
                        return $query
                            ->when(
                                $state['start_date'],
                                fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date)
                            )
                            ->when(
                                $state['end_date'],
                                fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date)
                            );
                    })
                    ->indicateUsing(function (array $state): ?string {
                        if (!$state['start_date'] || !$state['end_date']) {
                            return null;
                        }

                        return __('Custom Range: :start - :end', [
                            'start' => Carbon::parse($state['start_date'])->format('d, M, Y'),
                            'end' => Carbon::parse($state['end_date'])->format('d, M, Y'),
                        ]);
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    protected function getTableQuery(): Builder
    {
        return Sale::query()
            ->where('sale_type', 'cash')
            ->with('items.product')
            ->select('sales.*')
            ->selectRaw(<<<'SQL'
                (
                  SELECT SUM(si.quantity * p.purchase_price)
                  FROM sale_items si
                  JOIN products p ON si.product_id = p.id
                  WHERE si.sale_id = sales.id
                ) AS cost_raw
            SQL)
            ->selectRaw(<<<'SQL'
                (
                  sales.final_price
                  - (
                      SELECT SUM(si.quantity * p.purchase_price)
                      FROM sale_items si
                      JOIN products p ON si.product_id = p.id
                      WHERE si.sale_id = sales.id
                    )
                ) AS profit_raw
            SQL);
    }

    protected function applyPresetTimeFilter(Builder $query, string $timeRange): void
    {
        $now = Carbon::now();

        switch ($timeRange) {
            case 'today':
                $query->whereDate('created_at', $now->toDateString());
                break;
            case 'yesterday':
                $query->whereDate('created_at', $now->copy()->subDay()->toDateString());
                break;
            case 'this_week':
                $query->whereBetween('created_at', [
                    $now->copy()->startOfWeek(),
                    $now->copy()->endOfWeek(),
                ]);
                break;
            case 'last_week':
                $query->whereBetween('created_at', [
                    $now->copy()->subWeek()->startOfWeek(),
                    $now->copy()->subWeek()->endOfWeek(),
                ]);
                break;
            case 'this_month':
                $query->whereBetween('created_at', [
                    $now->copy()->startOfMonth(),
                    $now->copy()->endOfMonth(),
                ]);
                break;
            case 'last_month':
                $query->whereBetween('created_at', [
                    $now->copy()->subMonth()->startOfMonth(),
                    $now->copy()->subMonth()->endOfMonth(),
                ]);
                break;
            case 'this_year':
                $query->whereBetween('created_at', [
                    $now->copy()->startOfYear(),
                    $now->copy()->endOfYear(),
                ]);
                break;
            case 'last_year':
                $query->whereBetween('created_at', [
                    $now->copy()->subYear()->startOfYear(),
                    $now->copy()->subYear()->endOfYear(),
                ]);
                break;
        }
    }
}