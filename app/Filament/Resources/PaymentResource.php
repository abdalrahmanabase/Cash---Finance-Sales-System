<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Sale;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class PaymentResource extends Resource
{
    protected static ?string $model = Sale::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationLabel = null;
    protected static ?string $navigationGroup = null;
    protected static ?string $title = null;
    

    public static function getNavigationLabel(): string
    {
        return __('Payments Table');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Clients Management');
    }

    public static function getTitle(): string
    {
        return __('Payments Table');
    }

    public static function getModelLabel(): string
    {
        return __('Payment');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Payments');
    }
protected static function currencySymbol(): string
    {
        return app()->getLocale() === 'ar' ? 'جم.' : 'EGP';
    }


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('client.name')
                    ->label(__('Client Name'))
                    ->sortable()
                    ->searchable()
                    // Link to the ClientInstallmentPayments page, pre‐filtering by this client_id:
                    ->url(fn (Sale $record) => route('filament.pages.client-installment-payments', [
                        'tableFilters' => [
                            'client_id' => [
                                'value' => $record->client_id,
                            ],
                        ],
                    ]))
                    ->openUrlInNewTab(),

                TextColumn::make('next_payment_date')
                    ->label(__('Next Payment Date'))
                    ->date('d-m-Y')
                    ->sortable(),

                 TextColumn::make('monthly_installment')
                    ->label(__('Monthly Payment'))
                    ->getStateUsing(fn (Sale $sale) => 
                        number_format($sale->monthly_installment, 0, '.', ',') . ' ' . static::currencySymbol()
                    )
                    ->sortable(),

                TextColumn::make('amount_due')
                    ->label(__('Amount Due'))
                    ->getStateUsing(function (Sale $sale) {
                        $progress = $sale->getPaymentScheduleProgress();
                        $amt = $progress['next_payment_due'] > 0
                            ? $progress['next_payment_due']
                            : $sale->monthly_installment;
                        return number_format($amt, 0, '.', ',') . ' ' . static::currencySymbol();
                    }),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn ($state, Sale $record) => match (true) {
                        $record->isPaymentOverdue()    => 'danger',
                        $record->status === 'completed' => 'success',
                        default                         => 'primary',
                    })
                    ->formatStateUsing(function ($state, Sale $record) {
                        if ($record->status === 'completed') {
                            return __('Fully Paid');
                        }
                        if ($record->isPaymentOverdue()) {
                            return __('Overdue');
                        }
                        return __('Upcoming');
                    }),
            ])
            ->filters([
                SelectFilter::make('due_month')
                    ->label(__('By Due Month'))
                    ->options(
                        collect(range(-3, 3))
                            ->mapWithKeys(fn ($i) => [
                                Carbon::now()
                                    ->copy()
                                    ->addMonths($i)
                                    ->format('Y-m')
                                    => Carbon::now()
                                        ->copy()
                                        ->addMonths($i)
                                        ->translatedFormat('F Y'),
                            ])
                            ->toArray()
                        + [
                            'overdue' => __('Overdue'),
                            'all'     => __('All'),
                        ]
                    )
                    ->default(Carbon::now()->format('Y-m'))
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'] ?? Carbon::now()->format('Y-m');
                        if ($value === 'all') {
                            return $query;
                        }
                        if ($value === 'overdue') {
                            return $query
                                ->where('next_payment_date', '<', now()->format('Y-m-d'))
                                ->where('status', 'ongoing');
                        }
                        return $query->whereRaw("DATE_FORMAT(next_payment_date, '%Y-%m') = ?", [$value]);
                    }),
            ])
            ->defaultSort('next_payment_date', 'asc')
            ->actions([])
            ->bulkActions([])
            ->headerActions([
                Tables\Actions\Action::make('Go to Client Payments')
                    ->label(__('Client Payments'))
                    ->url(fn () => route('filament.pages.client-installment-payments'))
                    ->icon('heroicon-o-arrow-right'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        // Only show installment/ongoing by default
        return parent::getEloquentQuery()
            ->where('sale_type', 'installment')
            ->where('status', 'ongoing');
    }
}
