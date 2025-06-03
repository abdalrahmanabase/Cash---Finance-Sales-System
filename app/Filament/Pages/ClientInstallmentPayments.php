<?php

namespace App\Filament\Pages;

use App\Models\Client;
use App\Models\Sale;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Actions\Action as PageAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ClientInstallmentPayments extends Page implements HasTable
{
    use InteractsWithTable;

    public ?int $filterClient = null;

    protected static ?string $navigationIcon  = 'heroicon-o-credit-card';
    protected static ?string $navigationGroup = 'Clients Management';
    protected static ?string $title           = 'Client Payments';
    protected static ?string $slug            = 'client-installment-payments';
    protected static string  $view            = 'filament.pages.client-installment-payments';

    public function mount(): void
    {
        $this->filterClient = null;
    }

    public function updatedTableFilters(array $filters): void
    {
        if (isset($filters['client_id']['value'])) {
            $this->filterClient = $filters['client_id']['value'];
            $this->refresh();
        }
    }

    public function getActions(): array
    {
        return [
            PageAction::make('makePayment')
                ->label('Record Payment')
                ->icon('heroicon-o-credit-card')
                ->color('primary')
                ->modalHeading('Record Client Payment')
                ->modalWidth('xl')
                ->form([
                    Select::make('client_id')
                        ->label('Client')
                        ->options(Client::orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->reactive()
                        ->required()
                        ->afterStateUpdated(fn ($state, callable $set) => $set('sale_id', null)),

                    Select::make('sale_id')
                        ->label('Installment Sale')
                        ->options(fn (callable $get) => $get('client_id')
                            ? Sale::where('client_id', $get('client_id'))
                                  ->where('sale_type', 'installment')
                                  ->where('status', 'ongoing')
                                  ->get()
                                  ->mapWithKeys(fn (Sale $sale) => [
                                      $sale->id => new HtmlString(
                                          'Sale #' . $sale->id
                                          . ' – ' . number_format($sale->remaining_amount, 2) . ' EGP remaining'
                                          . ' (' . $sale->remaining_months . ' months left)'
                                      )
                                  ])
                            : []
                        )
                        ->reactive()
                        ->required()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if (! $state) {
                                return;
                            }
                            $sale = Sale::find($state);
                            if (! $sale) {
                                return;
                            }
                            $set('remaining_amount',       $sale->remaining_amount ?? 0);
                            $set('monthly_installment',    $sale->monthly_installment ?? 0);
                            $set('current_month_due',      $sale->current_month_due ?? 0);
                            $set('payment_amount', min(
                                $sale->current_month_due ?? 0,
                                $sale->remaining_amount  ?? 0
                            ));
                            $set('next_payment_date', optional($sale->next_payment_date)->format('d-m-Y'));
                        }),

                    TextInput::make('payment_amount')
                        ->label('Payment Amount (EGP)')
                        ->numeric()
                        ->required()
                        ->minValue(0.01)
                        ->maxValue(fn (callable $get) => optional(Sale::find($get('sale_id')))->remaining_amount)
                        ->step(0.01)
                        ->default(fn (callable $get) => optional(Sale::find($get('sale_id')))->current_month_due)
                        ->reactive(),

                    DatePicker::make('payment_date')
                        ->label('Payment Date')
                        ->default(now())
                        ->required()
                        ->maxDate(now()),

                    Placeholder::make('remaining_amount')
                        ->label('Remaining Amount')
                        ->content(fn (callable $get) =>
                            optional(Sale::find($get('sale_id')))->remaining_amount
                                ? number_format(optional(Sale::find($get('sale_id')))->remaining_amount, 2) . ' EGP'
                                : '-'
                        ),

                    Placeholder::make('monthly_installment')
                        ->label('Monthly Payment')
                        ->content(fn (callable $get) =>
                            optional(Sale::find($get('sale_id')))->monthly_installment
                                ? number_format(optional(Sale::find($get('sale_id')))->monthly_installment, 2) . ' EGP'
                                : '-'
                        ),

                    Placeholder::make('current_month_due')
                        ->label('Due This Month')
                        ->content(fn (callable $get) =>
                            optional(Sale::find($get('sale_id')))->current_month_due
                                ? number_format(optional(Sale::find($get('sale_id')))->current_month_due, 2) . ' EGP'
                                : '-'
                        ),

                    Placeholder::make('next_payment_date')
                        ->label('Next Payment Date')
                        ->content(fn (callable $get) =>
                            optional(Sale::find($get('sale_id')))->next_payment_date
                                ? optional(Sale::find($get('sale_id')))->next_payment_date->format('d-m-Y')
                                : '-'
                        ),
                ])
                ->action(function (array $data) {
                    $sale = Sale::find($data['sale_id']);

                    if (! $sale) {
                        Notification::make()
                            ->title('Sale not found')
                            ->danger()
                            ->send();
                        return;
                    }

                    if ($data['payment_amount'] > $sale->remaining_amount) {
                        Notification::make()
                            ->title('Payment too large')
                            ->body("Cannot exceed remaining of " . number_format($sale->remaining_amount, 2) . " EGP")
                            ->danger()
                            ->send();
                        return;
                    }

                    $success = $sale->recordPayment(
                        floatval($data['payment_amount']),
                        $data['payment_date']
                    );
                    $sale->refresh();

                    if ($success) {
                        Notification::make()
                            ->title('Payment Successful')
                            ->body(
                                "EGP " . number_format($data['payment_amount'], 2) . " recorded.\n"
                                . "Remaining: EGP " . number_format($sale->remaining_amount, 2) . "\n"
                                . "Months paid: "
                                . $sale->getPaymentScheduleProgress()['fully_paid_months']
                                . "/" . $sale->months_count
                            )
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Payment Failed')
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    protected function getTableQuery(): Builder
    {
        return Sale::query()
            ->whereNotNull('payment_dates')
            ->where('sale_type', 'installment')
            ->with('client');
    }

    protected function getTableFilteredQuery(): Builder
    {
        $query = $this->getTableQuery();

        if ($this->filterClient) {
            $query->where('client_id', $this->filterClient);
        }

        return $query;
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('client.name')
                ->label('Client')
                ->sortable()
                ->searchable(),

            TextColumn::make('latestPayment.amount')
                ->label('Last Payment')
                ->formatStateUsing(fn ($state) => $state ? 'EGP ' . number_format($state, 2) : '-')
                ->sortable(),

            TextColumn::make('latestPayment.date')
                ->label('Last Payment Date')
                ->sortable()
                ->formatStateUsing(fn ($state) => $state ? Carbon::parse($state)->format('d-m-Y') : '-'),

            TextColumn::make('remaining_amount')
                ->label('Remaining')
                ->money('egp')
                ->sortable(),

            TextColumn::make('status')
                ->label('Status')
                ->badge()
                ->color(fn (string $state) => match ($state) {
                    'completed' => 'success',
                    'ongoing'   => 'warning',
                    default     => 'gray',
                }),
        ];
    }

    protected function getTableFilters(): array
    {
        return [
            SelectFilter::make('client_id')
                ->label('Client')
                ->relationship('client', 'name'),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            TableAction::make('Edit Last Payment')
                ->icon('heroicon-o-pencil')
                ->modalHeading('Edit Last Payment')
                ->modalSubmitActionLabel('Save')
                ->form(function (Sale $record) {
                    $last = $record->getLatestPayment();
                    return [
                        TextInput::make('amount')
                            ->label('Payment Amount')
                            ->numeric()
                            ->default($last['amount'] ?? 0)
                            ->required()
                            ->minValue(0.01),

                        DatePicker::make('date')
                            ->label('Payment Date')
                            ->default($last['date'] ?? now())
                            ->required(),
                    ];
                })
                ->action(function (array $data, Sale $record) {
                    $last = $record->getLatestPayment();
                    if ($last) {
                        $index = null;
                        foreach ($record->all_payments as $i => $p) {
                            if (
                                $p['amount'] == $last['amount'] &&
                                $p['date']   == $last['date']
                            ) {
                                $index = $i;
                                break;
                            }
                        }
                        if ($index !== null) {
                            $success = $record->updatePayment($index, $data['amount'], $data['date']);
                            if ($success) {
                                Notification::make()
                                    ->title('Payment updated')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Failed to update payment')
                                    ->danger()
                                    ->send();
                            }
                        }
                    }
                }),

            TableAction::make('Delete Last Payment')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->color('danger')
                ->modalHeading('Delete Last Payment')
                ->action(function (Sale $record) {
                    $last = $record->getLatestPayment();
                    if ($last) {
                        $index = null;
                        foreach ($record->all_payments as $i => $p) {
                            if (
                                $p['amount'] == $last['amount'] &&
                                $p['date']   == $last['date']
                            ) {
                                $index = $i;
                                break;
                            }
                        }
                        if ($index !== null) {
                            $success = $record->deletePayment($index);
                            if ($success) {
                                Notification::make()
                                    ->title('Payment deleted')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Failed to delete payment')
                                    ->danger()
                                    ->send();
                            }
                        }
                    }
                }),
        ];
    }
}
