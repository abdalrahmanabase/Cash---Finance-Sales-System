<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Sale;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\BadgeColumn;
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
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        return $table
            ->query(function () {
                return Sale::query()
                    ->where('sale_type', 'installment')
                    ->where('status', 'ongoing');
            })
            ->modifyQueryUsing(function (Builder $query) {
                $sales = $query->get();
                return Sale::whereIn('id', $sales->pluck('id'));
            })
            ->columns([
                TextColumn::make('client.name')
                    ->label('Client Name')
                    ->sortable()
                    ->searchable()
                    ->url(fn ($record) => route('filament.pages.client-installment-payments', ['client' => $record->client_id]))
                    ->openUrlInNewTab(),

                TextColumn::make('next_payment_date')
                    ->label('Payment Date')
                    ->sortable()
                    ->formatStateUsing(function ($state, Sale $record) {
                        try {
                            $paymentDate = Carbon::createFromFormat('d-m-Y', $state);
                            $filterMonth = request('table.filters.month.value', Carbon::now()->month);
                            $filterYear = Carbon::now()->year;
                            
                            $isOverdue = $paymentDate->month < $filterMonth && 
                                         $paymentDate->year <= $filterYear;
                            
                            return $isOverdue ? $state . ' ⚠️' : $state;
                        } catch (\Exception $e) {
                            return $state;
                        }
                    }),

                TextColumn::make('amount_due')
                    ->label('Amount Due')
                    ->money('EGP')
                    ->sortable()
                    ->getStateUsing(function (Sale $record) {
                        $progress = $record->getPaymentScheduleProgress();
                        return $progress['next_payment_due'] > 0 ? $progress['next_payment_due'] : $record->monthly_installment;
                    }),

                TextColumn::make('payment_status')
                    ->label('Status')
                    ->formatStateUsing(function (Sale $record) {
                        try {
                            $paymentDate = Carbon::createFromFormat('d-m-Y', $record->next_payment_date);
                            $filterMonth = request('table.filters.month.value', Carbon::now()->month);
                            $filterYear = Carbon::now()->year;

                            $isOverdue = $paymentDate->month < $filterMonth && 
                                         $paymentDate->year <= $filterYear;

                            if ($isOverdue) {
                                return 'Overdue ⚠️';
                            }

                            return match ($record->dynamic_status) {
                                'danger' => 'Late (More than 3 days)',
                                'orange' => 'Late (Up to 3 days)',
                                'beige' => 'Partially Paid',
                                'completed' => 'Fully Paid',
                                'success' => 'Upcoming',
                                default => 'Unknown',
                            };
                        } catch (\Exception $e) {
                            return 'Unknown';
                        }
                    })
                    ->extraAttributes(function ($state) {
                        return [
                            'class' => match ($state) {
                                'Overdue ⚠️' => 'bg-red-500 text-white px-2 py-1 rounded',
                                'Late (More than 3 days)' => 'bg-red-500 text-white px-2 py-1 rounded',
                                'Late (Up to 3 days)' => 'bg-orange-500 text-white px-2 py-1 rounded',
                                'Partially Paid' => 'bg-yellow-500 text-white px-2 py-1 rounded',
                                'Fully Paid' => 'bg-green-500 text-white px-2 py-1 rounded',
                                'Upcoming' => 'bg-blue-500 text-white px-2 py-1 rounded',
                                default => 'bg-gray-500 text-white px-2 py-1 rounded',
                            },
                        ];
                    }),
            ])
            ->filters([
                SelectFilter::make('month')
                    ->options([
                        '1' => 'January',
                        '2' => 'February',
                        '3' => 'March',
                        '4' => 'April',
                        '5' => 'May',
                        '6' => 'June',
                        '7' => 'July',
                        '8' => 'August',
                        '9' => 'September',
                        '10' => 'October',
                        '11' => 'November',
                        '12' => 'December',
                    ])
                    ->default($currentMonth)
                    ->query(function (Builder $query, array $data) use ($currentYear) {
                        $month = $data['value'] ?? Carbon::now()->month;
                        
                        $sales = Sale::where('sale_type', 'installment')
                            ->where('status', 'ongoing')
                            ->get();
                        
                        $filteredIds = $sales->filter(function ($sale) use ($month, $currentYear) {
                            try {
                                $paymentDate = Carbon::createFromFormat('d-m-Y', $sale->next_payment_date);
                                $isDueThisMonth = $paymentDate->month == $month && $paymentDate->year == $currentYear;
                                $isOverdue = $paymentDate->month < $month && $paymentDate->year <= $currentYear;
                                return $isDueThisMonth || $isOverdue;
                            } catch (\Exception $e) {
                                return false;
                            }
                        })->pluck('id');
                        
                        $query->whereIn('id', $filteredIds);
                    })
                    ->label('Filter by Month'),
            ])
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
        return parent::getEloquentQuery()
            ->where('sale_type', 'installment')
            ->where('status', 'ongoing');
    }
}