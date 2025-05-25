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

class SalesProfitSummary extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static string $view = 'filament.pages.sales-profit-summary';
    protected static ?string $title = 'Sales Profit Summary';
    protected static ?string $navigationGroup = 'Sales Management';

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
                    ->label('Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('final_price')
                    ->label('Sale Amount')
                    ->money('EGP')
                    ->sortable(),

                TextColumn::make('total_cost')
                    ->label('Cost')
                    ->money('EGP')
                    ->sortable(),

                TextColumn::make('profit')
                    ->label('Profit')
                    ->money('EGP')
                    ->color(fn($record) => $record->profit >= 0 ? 'success' : 'danger')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('timeRange')
                    ->label('Time Range')
                    ->options([
                        'day' => 'Today',
                        'week' => 'This Week',
                        'month' => 'This Month',
                        'year' => 'This Year',
                    ])
                    ->default('month')
                    ->query(function (Builder $query, array $state) {    
                        $value = $state['value'] ?? 'month';
                        $this->applyTimeFilter($query, $value);
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    protected function getTableQuery(): Builder
    {
        $query = Sale::query()
            ->where('sale_type', 'cash')
            ->with(['items.product'])
            ->selectRaw('
                sales.*,
                (SELECT SUM(sale_items.quantity * products.purchase_price) 
                 FROM sale_items 
                 JOIN products ON sale_items.product_id = products.id 
                 WHERE sale_items.sale_id = sales.id) as total_cost,
                (sales.final_price - (SELECT SUM(sale_items.quantity * products.purchase_price) 
                 FROM sale_items 
                 JOIN products ON sale_items.product_id = products.id 
                 WHERE sale_items.sale_id = sales.id)) as profit
            ');

        // Apply default filter if none is selected
        if (!request()->has('tableFilters.timeRange.value')) {
            $this->applyTimeFilter($query, 'month');
        }

        return $query;
    }

    protected function applyTimeFilter(Builder $query, string $timeRange): void
    {
        $now = Carbon::now();

        switch ($timeRange) {
            case 'day':
                $query->whereDate('created_at', $now->toDateString());
                break;
            case 'week':
                $query->whereBetween('created_at', [
                    $now->copy()->startOfWeek(),
                    $now->copy()->endOfWeek()
                ]);
                break;
            case 'month':
                $query->whereBetween('created_at', [
                    $now->copy()->startOfMonth(),
                    $now->copy()->endOfMonth()
                ]);
                break;
            case 'year':
                $query->whereBetween('created_at', [
                    $now->copy()->startOfYear(),
                    $now->copy()->endOfYear()
                ]);
                break;
        }
    }
}