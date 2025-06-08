<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Forms\Components\{Select, TextInput, DatePicker, Placeholder};
use App\Models\{Client, Sale};
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ClientInstallmentPayments extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static string $view = 'filament.pages.client-installment-payments';
    protected static ?string $navigationGroup = 'Clients Management';
    protected static ?int $navigationSort = 10;
    protected static ?string $title = 'Client Payments';

    public static function getNavigationGroup(): ?string
    {
        return __('Clients Management');
    }

    public function getTitle(): string
    {
        return __('Client Payments');
    }

    public $editingPayment = null;
    public $deletingPayment = null;
    public ?array $form = [];

    protected function getViewData(): array
    {
        return [
            'allPayments' => $this->getAllPaymentsFiltered(),
        ];
    }

     public function getActions(): array
    {
        return [
            Action::make('makePayment')
                ->label('Make Payment')
                ->icon('heroicon-o-credit-card')
                ->color('primary')
                ->modalHeading('Record Client Payment')
                ->modalWidth('xl')
                ->button()
                ->form([
                    Select::make('clientId')
                        ->label('Client')
                        ->options(Client::pluck('name', 'id'))
                        ->searchable()
                        ->reactive()
                        ->required()
                        ->afterStateUpdated(fn ($state, callable $set) => $set('saleId', null)),

                    Select::make('saleId')
                        ->label('Installment Sale')
                        ->options(fn (callable $get) => Sale::where('client_id', $get('clientId'))
                            ->where('sale_type', 'installment')
                            ->where('status', 'ongoing')
                            ->get()
                            ->mapWithKeys(fn ($sale) => [
                                $sale->id => new HtmlString(
                                    'Sale #' . $sale->id .
                                    ' - ' . number_format($sale->remaining_amount, 2) . ' EGP remaining' .
                                    ' (' . $sale->remaining_months . ' months left)'
                                )
                            ]))
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if (!$state) return;
                            $sale = Sale::find($state);
                            if (!$sale) return;
                            $set('remainingAmount', $sale->remaining_amount ?? 0);
                            $set('monthlyInstallment', $sale->monthly_installment ?? 0);
                            $set('currentMonthDue', $sale->current_month_due ?? 0);
                            $set('paymentAmount', min(
                                $sale->current_month_due ?? 0,
                                $sale->remaining_amount ?? 0
                            ));
                            $set('fullyPaidMonths', $sale->getPaymentScheduleProgress()['fully_paid_months'] ?? 0);
                        }),

                    TextInput::make('paymentAmount')
                        ->label('Payment Amount (EGP)')
                        ->numeric()
                        ->required()
                        ->minValue(0.01)
                        ->maxValue(function (callable $get) {
                            $sale = Sale::find($get('saleId'));
                            return $sale ? $sale->remaining_amount : 0;
                        })
                        ->step(0.01)
                        ->default(fn (callable $get) => optional(Sale::find($get('saleId')))->current_month_due)
                        ->reactive()
                        ->rules([
                            function (callable $get) {
                                return function (string $attribute, $value, $fail) use ($get) {
                                    $sale = Sale::find($get('saleId'));
                                    if ($sale && $value > $sale->remaining_amount) {
                                        $fail("Payment amount cannot exceed remaining amount of " . number_format($sale->remaining_amount, 2) . " EGP");
                                    }
                                };
                            }
                        ]),

                    DatePicker::make('paymentDate')
                        ->label('Payment Date')
                        ->default(now())
                        ->required()
                        ->maxDate(now()),

                    TextInput::make('remainingAmount')
                        ->label('Remaining Amount')
                        ->disabled()
                        ->reactive(),

                    TextInput::make('monthlyInstallment')
                        ->label('Monthly Payment')
                        ->disabled()
                        ->reactive(),

                    TextInput::make('currentMonthDue')
                        ->label('Due for This Month')
                        ->default(fn (callable $get) => optional(Sale::find($get('saleId')))->current_month_due)
                        ->disabled()
                        ->reactive(),

                    TextInput::make('fullyPaidMonths')
                        ->label('Fully Paid Months')
                        ->disabled()
                        ->reactive(),

                    Placeholder::make('paymentHistory')
                        ->label('Payment History')
                        ->content(function (callable $get) {
                            $sale = Sale::find($get('saleId'));
                            if (!$sale || empty($sale->payment_dates)) {
                                return 'No payments recorded yet.';
                            }

                            $history = '';
                            $payments = $sale->getAllPaymentsAttribute();

                            foreach ($payments as $payment) {
                                $history .= '<div class="flex justify-between border-b pb-1">';
                                $history .= '<span>' . Carbon::parse($payment['date'])->format('d-m-Y') . '</span>';
                                $history .= '<span class="font-medium">EGP ' . number_format($payment['amount'], 2) . '</span>';
                                $history .= '</div>';
                            }

                            return new HtmlString('<div class="space-y-2">' . $history . '</div>');
                        })
                        ->columnSpanFull()
                        ->reactive(),
                ])
                ->action(function (array $data) {
                    $sale = Sale::find($data['saleId']);

                    if (!$sale) {
                        Notification::make()
                            ->title('Sale not found')
                            ->danger()
                            ->send();
                        return;
                    }

                    if ($data['paymentAmount'] > $sale->remaining_amount) {
                        Notification::make()
                            ->title('Payment amount too large')
                            ->body("Payment cannot exceed remaining amount of " . number_format($sale->remaining_amount, 2) . " EGP")
                            ->danger()
                            ->send();
                        return;
                    }

                    $success = $sale->recordPayment(
                        floatval($data['paymentAmount']),
                        $data['paymentDate']
                    );


                    if ($success) {
                        $sale->refresh();
                        $status = $sale->getPaymentStatus();
                        $progress = $sale->getPaymentScheduleProgress();

                        Notification::make()
                            ->title('Payment Successful')
                            ->body(
                                "Payment of EGP " . number_format($data['paymentAmount'], 2) . " recorded.\n" .
                                "Remaining: EGP " . number_format($status['remaining_amount'], 2) . "\n" .
                                "Months paid: " . $progress['fully_paid_months'] . "/" . $sale->months_count
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
    
    public function getRecentPayments($limit = 10)
    {
        return Sale::with('client')
            ->whereNotNull('payment_dates')
            ->where('sale_type', 'installment')
            ->orderByDesc('updated_at')
            ->take($limit)
            ->get()
            ->map(function ($sale) {
                $payments = $sale->getAllPaymentsAttribute();
                $lastPayment = end($payments);
                
                return [
                    'client' => $sale->client?->name ?? 'Unknown',
                    'amount' => $lastPayment['amount'] ?? null,
                    'date' => $lastPayment['date'] ?? null,
                    'sale_id' => $sale->id,
                ];
            })
            ->filter(fn($row) => $row['amount'] !== null && $row['date'] !== null);
    }

    public function editPaymentAction($saleId, $paymentIndex)
{
    $this->editingPayment = ['saleId' => $saleId, 'paymentIndex' => $paymentIndex];

    $sale = Sale::find($saleId);
    if ($sale) {
        $payment = $sale->getAllPaymentsAttribute()[$paymentIndex] ?? null;
        if ($payment) {
            $this->form = [
                'amount' => $payment['amount'],
                'date' => $payment['date'],
            ];
        }
    }

    $this->dispatch('open-modal', id: 'edit-payment');
}


    public function deletePaymentAction($saleId, $paymentIndex)
    {
        $this->deletingPayment = ['saleId' => $saleId, 'paymentIndex' => $paymentIndex];
        $this->dispatch('open-modal', id: 'delete-payment');
    }

    public function updatePayment()
    {
        $data = $this->form;
        $sale = Sale::find($this->editingPayment['saleId']);

        if ($sale->updatePayment(
            $this->editingPayment['paymentIndex'],
            $data['amount'],
            $data['date']
        )) {
            $sale->refresh();

        Notification::make()
            ->title('Payment Updated')
            ->success()
            ->send();

            $this->editingPayment = null;
            $this->form = [];
            $this->dispatch('close-modal', id: 'edit-payment');
        } else {
            Notification::make()
                ->title('Failed to update payment')
                ->danger()
                ->send();
        }
    }

    public function confirmDeletePayment()
    {
        $sale = Sale::find($this->deletingPayment['saleId']);

        if ($sale->deletePayment($this->deletingPayment['paymentIndex'])) {
            $sale->refresh();

        Notification::make()
            ->title('Payment Deleted')
            ->success()
            ->send();

            $this->deletingPayment = null;
            $this->dispatch('close-modal', id: 'delete-payment');
        } else {
            Notification::make()
                ->title('Failed to delete payment')
                ->danger()
                ->send();
        }
    }





public function getAllPaymentsFiltered(int $perPage = 10)
{
    $query = Sale::with('client')
        ->where('sale_type', 'installment');

    if (request('client')) {
        $query->where('client_id', request('client'));
    }

    $payments = collect();

    foreach ($query->get() as $sale) {
        $salePayments = $sale->getAllPaymentsAttribute();
        foreach ($salePayments as $index => $payment) {
            $payments->push([
                'client' => $sale->client?->name ?? 'Unknown',
                'amount' => $payment['amount'],
                'date' => $payment['date'],
                'timestamp' => $payment['timestamp'] ?? $payment['created_at'] ?? $payment['date'],
                'sale_id' => $sale->id,
                'payment_index' => $index,
            ]);
        }
    }

    // Sort payments by timestamp DESC (latest first)
    $payments = $payments
    ->sortByDesc(fn ($p) => sprintf('%s-%03d', $p['date'], 999 - $p['payment_index']))
    ->values();



    // Paginate
    $page = request('page', 1);
    $total = $payments->count();

    return new LengthAwarePaginator(
        $payments->forPage($page, $perPage),
        $total,
        $perPage,
        $page,
        ['path' => request()->url(), 'query' => request()->query()]
    );
}


}