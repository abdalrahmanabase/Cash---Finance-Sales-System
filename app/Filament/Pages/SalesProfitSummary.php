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
    protected static ?string $title = 'Cash Sales Profit Summary';
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
                    ->date('d-m-Y')
                    ->sortable(),

                TextColumn::make('final_price')
                    ->label('Sale Amount')
                    ->money('EGP')
                    ->sortable()
                    // ->summarize([
                    //     Tables\Columns\Summarizers\Sum::make()
                    //         ->money('EGP')
                    //         ->label('Total Sales')
                    // ])
                    ,

                TextColumn::make('total_cost')
                    ->label('Cost')
                    ->money('EGP')
                    ->sortable()
                    // ->summarize([
                    //     Tables\Columns\Summarizers\Sum::make()
                    //         ->money('EGP')
                    //         ->label('Total Cost')
                    // ])
                    ,

                TextColumn::make('profit')
                    ->label('Profit')
                    ->money('EGP')
                    ->color(fn($record) => $record->profit >= 0 ? 'success' : 'danger')
                    ->sortable()
                    // ->summarize([
                    //     Tables\Columns\Summarizers\Sum::make()
                    //         ->money('EGP')
                    //         ->label('Total Profit'), // NO color here
                    // ])
                    ,

            ])
            ->filters([
                // Preset time ranges
                Tables\Filters\SelectFilter::make('preset_time_range')
                    ->label('Quick Date Range')
                    ->options([
                        'today' => 'Today',
                        'yesterday' => 'Yesterday',
                        'this_week' => 'This Week',
                        'last_week' => 'Last Week',
                        'this_month' => 'This Month',
                        'last_month' => 'Last Month',
                        'this_year' => 'This Year',
                        'last_year' => 'Last Year',
                    ])
                    ->default('all')
                    ->query(function (Builder $query, $state) {
                        if ($state === 'all' || empty($state)) {
                            // No date filter for 'all'
                            return;
                        }
                        
                        $this->applyPresetTimeFilter($query, $state['value']);
                    }),
                    
                // Custom date range filter
                Filter::make('custom_date_range')
    ->form([
        DatePicker::make('start_date')
            ->label('From Date'),
        DatePicker::make('end_date')
            ->label('To Date')
            ->default(now()), // 👈 Set default to today
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

        return 'Custom Range: ' .
            Carbon::parse($state['start_date'])->format('d, M, Y') .
            ' - ' .
            Carbon::parse($state['end_date'])->format('d, M, Y');
    }),

                    
            ])
            ->defaultSort('created_at', 'desc');
    }

    protected function getTableQuery(): Builder
    {
        return Sale::query()
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
                $startOfWeek = $now->copy()->startOfDay()->subDays(($now->dayOfWeek + 1) % 7); // Saturday
                $endOfWeek = $startOfWeek->copy()->addDays(6)->endOfDay(); // Friday
                $query->whereBetween('created_at', [$startOfWeek, $endOfWeek]);
                break;

            case 'last_week':
                $startOfLastWeek = $now->copy()->startOfDay()->subDays(($now->dayOfWeek + 1) % 7 + 7); // Last Saturday
                $endOfLastWeek = $startOfLastWeek->copy()->addDays(6)->endOfDay(); // Last Friday
                $query->whereBetween('created_at', [$startOfLastWeek, $endOfLastWeek]);
                break;
            case 'this_month':
                $query->whereBetween('created_at', [
                    $now->copy()->startOfMonth(),
                    $now->copy()->endOfMonth()
                ]);
                break;
            case 'last_month':
                $query->whereBetween('created_at', [
                    $now->copy()->subMonth()->startOfMonth(),
                    $now->copy()->subMonth()->endOfMonth()
                ]);
                break;
            case 'this_year':
                $query->whereBetween('created_at', [
                    $now->copy()->startOfYear(),
                    $now->copy()->endOfYear()
                ]);
                break;
            case 'last_year':
                $query->whereBetween('created_at', [
                    $now->copy()->subYear()->startOfYear(),
                    $now->copy()->subYear()->endOfYear()
                ]);
                break;
        }
    }

    protected function isSaleTypeFilterHidden(): bool
    {
        // Hide if you want to force only cash sales
        return true;
    }
}