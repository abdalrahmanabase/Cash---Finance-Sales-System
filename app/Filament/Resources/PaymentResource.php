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
    protected static ?string $modelLabel = 'Payment';
    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?string $navigationGroup = 'Clients Management';
    protected static ?string $navigationLabel = 'Payments Table';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('client.name')
                    ->label('Client Name')
                    ->sortable()
                    ->searchable()
                    ->url(fn ($record) => route('filament.pages.client-installment-payments', ['client' => $record->client_id]))
                    ->openUrlInNewTab(),

                TextColumn::make('next_payment_date')
                    ->label('Next Payment Date')
                    ->date('d-m-Y')
                    ->sortable(),

                TextColumn::make('monthly_installment')
                    ->label('Monthly Payment')
                    ->money('EGP')
                    ->sortable(),

                TextColumn::make('amount_due')
                    ->label('Amount Due')
                    ->money('EGP')
                    ->getStateUsing(function (Sale $record) {
                        $progress = $record->getPaymentScheduleProgress();
                        return $progress['next_payment_due'] > 0 ? $progress['next_payment_due'] : $record->monthly_installment;
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state, Sale $record) => match (true) {
                        $record->isPaymentOverdue() => 'danger',
                        $record->status === 'completed' => 'success',
                        default => 'primary',
                    })
                    ->formatStateUsing(function ($state, Sale $record) {
                        if ($record->status === 'completed') {
                            return 'Fully Paid';
                        }
                        if ($record->isPaymentOverdue()) {
                            return 'Overdue';
                        }
                        return 'Upcoming';
                    })
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('due_month')
                    ->label('By Due Month')
                    ->options(
                        collect(range(-3, 3))
                            ->mapWithKeys(fn ($i) => [
                                Carbon::now()->copy()->addMonths($i)->format('Y-m') => Carbon::now()->copy()->addMonths($i)->translatedFormat('F Y')
                            ])
                            ->toArray()
                        + [
                            'overdue' => 'Overdue',
                            'all' => 'All',
                        ]
                    )
                    ->default(Carbon::now()->format('Y-m'))
                    ->query(function (Builder $query, array $data) {
                        $value = $data['value'] ?? Carbon::now()->format('Y-m');
                        if ($value === 'all') {
                            // No filter, show all
                            return $query;
                        }
                        if ($value === 'overdue') {
                            return $query->where('next_payment_date', '<', now()->format('Y-m-d'))
                                         ->where('status', 'ongoing');
                        }
                        // Otherwise, filter by month
                        return $query->whereRaw("DATE_FORMAT(next_payment_date, '%Y-%m') = ?", [$value]);
                    }),
            ])
            ->defaultSort('next_payment_date', 'asc')
            ->actions([])
            ->bulkActions([])
            ->headerActions([
                Tables\Actions\Action::make('Go to Client Payments')
                    ->label('Client Payments')
                    ->url(route('filament.pages.client-installment-payments'))
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
