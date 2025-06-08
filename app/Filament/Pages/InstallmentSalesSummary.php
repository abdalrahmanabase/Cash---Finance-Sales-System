<?php

namespace App\Filament\Pages;

use App\Models\Sale;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use App\Filament\Widgets\InstallmentSalesStats;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;

class InstallmentSalesSummary extends Page implements HasTable
{
    use InteractsWithTable;

    public $selectedMonth;

    protected static ?string $navigationIcon  = 'heroicon-o-chart-bar';
    protected static string  $view            = 'filament.pages.installment-sales-summary';
    protected static ?string $title           = 'Installment Payments Summary';
    protected static ?string $navigationGroup = 'Sales Management';

    public static function getNavigationGroup(): ?string
    {
        return __('Sales Management');
    }

    public function getTitle(): string
    {
        return __('Installment Payments Summary');
    }

    public function mount(): void
    {
        $currentMonth = now()->format('Y-m');
        $hasSales = Sale::where('sale_type', 'installment')
            ->where('status', 'ongoing')
            ->whereRaw("DATE_FORMAT(next_payment_date, '%Y-%m') = ?", [$currentMonth])
            ->exists();

        $this->selectedMonth = $hasSales ? $currentMonth : 'all_upcoming';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            InstallmentSalesStats::class,
        ];
    }

    protected function getHeaderWidgetsData(): array
    {
        return [
            InstallmentSalesStats::class => [
                'selectedMonth' => $this->selectedMonth,
            ],
        ];
    }

    // THIS is the correct method for syncing filter and property!
    public function updatedTableFilters($filters): void
{
    if (is_array($filters) && isset($filters['month_filter']['value'])) {
        $this->selectedMonth = $filters['month_filter']['value'];
        $this->refresh(); // <--- This will re-render the page & widgets.
    }
}

    protected function getMonthOptions(): array
    {
        $options = [
            'all_upcoming' => 'All Upcoming',
            'overdue'      => 'Overdue',
            'completed'    => 'Completed',
        ];

        for ($i = -1; $i <= 7; $i++) {
            $date  = now()->copy()->addMonths($i)->startOfMonth();
            $key   = $date->format('Y-m');
            $label = $date->translatedFormat('F Y');
            if ($i === 0) {
                $label .= ' (Current)';
            }
            $options[$key] = $label;
        }

        $options['all'] = 'All Sales';
        return $options;
    }

    public static function filterSalesByMonth(Builder $query, $month): Builder
    {
        $now = now();
        return match ($month) {
            'all_upcoming' => $query
                ->where('status', 'ongoing')
                ->whereDate('next_payment_date', '>=', $now->toDateString()),
            'overdue' => $query
                ->where('status', 'ongoing')
                ->whereDate('next_payment_date', '<', $now->toDateString()),
            'completed' => $query
                ->where('status', 'completed'),
            'all' => $query,
            default => $query
                ->where('status', 'ongoing')
                ->whereRaw("DATE_FORMAT(next_payment_date, '%Y-%m') = ?", [$month]),
        };
    }

    protected function getTableQuery(): Builder
    {
        return Sale::query()
            ->where('sale_type', 'installment')
            ->with(['client', 'items.product']);
    }

    protected function getTableFilteredQuery(): Builder
    {
        return static::filterSalesByMonth($this->getTableQuery(), $this->selectedMonth);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('client.name')
                ->label('Client')
                ->sortable()
                ->searchable(),

            TextColumn::make('monthly_installment')
                ->label('Installment')
                ->money('EGP')
                ->sortable(),

            // Add this column to show "Due Amount"
TextColumn::make('current_month_due')
    ->label('Due Amount')
    ->getStateUsing(
        fn (Sale $record): string => number_format(
            $record->getPaymentScheduleProgress()['next_payment_due'],
            2
        )
    )
    ->suffix(' EGP')
    ->color('blue'),

TextColumn::make('profit')
    ->label('Profit on Due Amount')
    ->getStateUsing(
        fn (Sale $record): string => number_format(
            $record->getProfitOnDueAmount(),
            2
        )
    )
    ->suffix(' EGP')
    ->color('green'),



            TextColumn::make('capital_due')
    ->label('Capital on Due Amount')
    ->getStateUsing(
        fn (Sale $record): string => number_format(
            $record->getCapitalOnDueAmount(),
            2
        )
    )
    ->suffix(' EGP')
    ->color('gray'),


            TextColumn::make('next_payment_date')
                ->label('Due Date')
                ->date()
                ->sortable(),

            TextColumn::make('status')
                ->label('Status')
                ->getStateUsing(fn (Sale $record) => match (true) {
                    str_starts_with($record->dynamic_status, 'Partial') => '🟡 ' . $record->dynamic_status,
                    $record->dynamic_status === 'completed'           => '✅ Completed',
                    $record->dynamic_status === 'danger'              => '❌ Overdue',
                    $record->dynamic_status === 'orange'              => '⚠️ Late',
                    default                                            => '🟢 On Track',
                }),
        ];
    }

    protected function getTableFilters(): array
    {
        return [
            SelectFilter::make('month_filter')
                ->label('By Due Month')
                ->options($this->getMonthOptions())
                ->default($this->selectedMonth)
                ->query(fn (Builder $query, array $data) =>
                    static::filterSalesByMonth($query, $data['value'] ?? now()->format('Y-m'))
                ),
        ];
    }

    protected function getTableDefaultSort(): array
    {
        return ['next_payment_date', 'asc'];
    }
}
