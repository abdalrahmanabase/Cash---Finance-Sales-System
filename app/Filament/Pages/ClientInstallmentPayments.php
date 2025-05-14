<?php
namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Forms\Components\{Select, TextInput, DatePicker};
use App\Models\{Client, Sale};
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

class ClientInstallmentPayments extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static string $view = 'filament.pages.client-installment-payments';
    protected static ?string $navigationGroup = 'Clients Management';
    protected static ?int $navigationSort = 10;
    protected static ?string $title = 'Client Payments';

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
                                $sale->id => new HtmlString('Sale #' . $sale->id . ' - ' . number_format($sale->remaining_amount, 2) . ' EGP remaining')
                            ]))
                        ->required(),

                    TextInput::make('paymentAmount')
                        ->label('Payment Amount (EGP)')
                        ->numeric()
                        ->default(fn (callable $get) => optional(Sale::find($get('saleId')))->monthly_installment)
                        ->required()
                        ->minValue(1)
                        ->step(0.01),

                    DatePicker::make('paymentDate')
                        ->label('Payment Date')
                        ->default(now())
                        ->required()
                        ->maxDate(now()),
                ])
                ->action(function (array $data) {
                    $sale = Sale::find($data['saleId']);
                    
                    if (!$sale) {
                        Notification::make()->title('Sale not found')->danger()->send();
                        return;
                    }

                    if ($sale->recordPayment($data['paymentAmount'], $data['paymentDate'])) {
                        $status = $sale->getPaymentStatus();
                        
                        Notification::make()
                            ->title('Payment Successful')
                            ->body("Payment of EGP {$data['paymentAmount']} recorded.\nRemaining: EGP {$status['remaining_amount']}")
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
}
