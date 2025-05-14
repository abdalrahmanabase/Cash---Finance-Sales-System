<?php

namespace App\Filament\Resources;

use App\Models\Product;
use App\Models\Sale;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Fieldset;
use Illuminate\Support\Carbon;
use App\Filament\Resources\ClientResource;
use App\Filament\Resources\ProductResource;

class InstallmentSaleResource extends Resource
{
    protected static ?string $model = Sale::class;
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationGroup = 'Sales Management';
    protected static ?string $navigationLabel = 'Installment Sales';
    protected static ?string $slug = 'sales/installment';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Sale Information')
                    ->schema([
                        Hidden::make('sale_type')->default('installment'),
                        Hidden::make('status')->default('ongoing'),
                        Select::make('client_id')
                            ->label('Client')
                            ->relationship('client', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->suffixAction(
                                fn () => \Filament\Forms\Components\Actions\Action::make('createClient')
                                    ->label('Create Client')
                                    ->icon('heroicon-o-plus')
                                    ->url(route('filament.admin.resources.clients.create'))
                            ),
                        Repeater::make('items')
                            ->relationship()
                            ->minItems(1)
                            ->required()
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->relationship('product', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->suffixAction(
                                        fn () => \Filament\Forms\Components\Actions\Action::make('createProduct')
                                            ->label('Create Product')
                                            ->icon('heroicon-o-plus')
                                            ->url(route('filament.admin.resources.products.create'))
                                    )
                                    ->afterStateHydrated(function ($state, Set $set) {
                                        if (!$state) return;
                                        $product = Product::find($state);
                                        $set('available_stock', $product->stock ?? 0);
                                    })
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if (!$state) return;
                                        $product = Product::find($state);
                                        $set('unit_price', $product->cash_price);
                                        $set('available_stock', $product->stock ?? 0);
                                    })
                                    ->required(),
                                TextInput::make('quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        $quantity = $get('quantity');
                                        $productId = $get('product_id');
                                        $unitPrice = $get('unit_price');
                                        if ($productId) {
                                            $product = Product::find($productId);
                                            if ($product && $quantity > $product->stock) {
                                                Notification::make()
                                                    ->title('Insufficient stock')
                                                    ->body("Only {$product->stock} available")
                                                    ->danger()
                                                    ->send();
                                                $quantity = $product->stock;
                                                $set('quantity', $quantity);
                                            }
                                        }
                                        $set('total', $quantity * $unitPrice);
                                        InstallmentSaleResource::updateTotals($get, $set);
                                    })
                                    ->required(),
                                TextInput::make('unit_price')
                                    ->numeric()
                                    ->prefix('EGP')
                                    ->disabled()
                                    ->dehydrated(),
                                TextInput::make('available_stock')
                                    ->label('Available Stock')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('total')
                                    ->label('Item Total')
                                    ->numeric()
                                    ->prefix('EGP')
                                    ->disabled()
                                    ->dehydrated(),
                            ])
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => InstallmentSaleResource::updateTotals($get, $set))
                            ->columnSpanFull(),
                        Hidden::make('total_price')->dehydrated(),
                        Select::make('discount_type')
                            ->label('Discount Type')
                            ->options([
                                'fixed' => 'Fixed (EGP)',
                                'percent' => 'Percent (%)',
                            ])
                            ->default('fixed')
                            ->dehydrated(false)
                            ->live(),
                        TextInput::make('discount_value')
                            ->label('Discount')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => InstallmentSaleResource::updateTotals($get, $set)),
                        Hidden::make('discount')->dehydrated(true),
                        TextInput::make('final_price')
                            ->label('Final Price')
                            ->numeric()
                            ->prefix('EGP')
                            ->disabled()
                            ->dehydrated(),
                        Hidden::make('remaining_amount')
                            ->default(function (Get $get) {
                                $finalPrice = $get('final_price') ?? 0;
                                $downPayment = $get('down_payment') ?? 0;
                                $interestAmount = $get('interest_amount') ?? 0;
                                return max(0, ($finalPrice - $downPayment) + $interestAmount);
                            })
                            ->dehydrated(),
                    ])
                    ->columns(2),

                Section::make('Installment Plan')
                    ->schema([
                         TextInput::make('down_payment')
                        ->numeric()
                        ->required()
                        ->default(0)
                        ->live(onBlur: true)
                        ->dehydrated(true)
                        ->afterStateHydrated(function (Set $set, $state) {
                            // Hydrate initial down payment value
                            $set('down_payment', floatval($state ?? 0));
                        })
                        ->afterStateUpdated(function (Get $get, Set $set) {
                            $downPayment = floatval($get('down_payment'));
                            $finalPrice = floatval($get('final_price') ?? 0);

                            if ($downPayment > $finalPrice) {
                                Notification::make()
                                    ->title('Invalid down payment')
                                    ->body('Down payment cannot exceed the final price')
                                    ->danger()
                                    ->send();
                                $downPayment = $finalPrice;
                                $set('down_payment', $finalPrice);
                            }
                            
                            static::updateInstallmentCalculations($get, $set);
                        }),
                        TextInput::make('interest_rate')
                            ->label('Interest Rate')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(1000)
                            ->default(0)
                            ->suffix('%')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => InstallmentSaleResource::updateInstallmentCalculations($get, $set))
                            ->required(),
                        TextInput::make('interest_amount')
                            ->label('Interest Amount')
                            ->numeric()
                            ->prefix('EGP')
                            ->disabled()
                            ->dehydrated(),
                        TextInput::make('months_count')
                            ->label('Installment Months')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->default(12)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => InstallmentSaleResource::updateInstallmentCalculations($get, $set))
                            ->required(),
                        TextInput::make('monthly_installment')
                            ->label('Monthly Installment')
                            ->numeric()
                            ->prefix('EGP')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => InstallmentSaleResource::updateInstallmentFromMonthly($get, $set))
                            ->required(),
                        Hidden::make('payment_dates')->default([]),
                        Hidden::make('payment_amounts')->default([]),
                    ])
                    ->columns(2),

                Section::make('Payment Summary')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                Placeholder::make('paid_amount')
                                    ->label('Paid Amount')
                                    ->content(function ($record) {
                                        $paid = $record?->paid_amount ?? 0;
                                        return 'EGP ' . number_format($paid, 2);
                                    })
                                    ->extraAttributes(['class' => 'text-center p-4 bg-green-50 rounded-lg border border-green-200']),
                                Placeholder::make('remaining_amount')
                                    ->label('Remaining Amount')
                                    ->content(function ($record) {
                                        $remaining = $record?->remaining_amount ?? ($record?->final_price ?? 0);
                                        return 'EGP ' . number_format($remaining, 2);
                                    })
                                    ->extraAttributes(['class' => 'text-center p-4 bg-blue-50 rounded-lg border border-blue-200']),
                                Placeholder::make('down_payment')
                                    ->label('Down Payment')
                                    ->content(function ($record) {
                                        $down = $record?->down_payment ?? 0;
                                        return 'EGP ' . number_format($down, 2);
                                    })
                                    ->extraAttributes(['class' => 'text-center p-4 bg-yellow-50 rounded-lg border border-yellow-200']),
                                Placeholder::make('remaining_months')
                                    ->label('Remaining Months')
                                    ->content(function ($record) {
                                        if (!$record) return '0';
                                        return $record->remaining_months;
                                    })
                                    ->extraAttributes(['class' => 'text-center p-4 bg-yellow-50 rounded-lg border border-yellow-200']),
                            ]),
                        Fieldset::make('Payment History')
                            ->schema([
                                Placeholder::make('payment_history')
                                    ->content(function ($record) {
                                        if (!$record) return 'No payments recorded yet.';
                                        $payments = $record->all_payments;
                                        if (empty($payments)) return 'No payments recorded yet.';
                                        $html = '<div class="space-y-2">';
                                        foreach ($payments as $payment) {
                                            $html .= '<div class="flex justify-between border-b pb-1">';
                                            $html .= '<span>' . \Carbon\Carbon::parse($payment['date'])->format('d-m-Y') . '</span>';
                                            $html .= '<span class="font-medium">EGP ' . number_format($payment['amount'], 2) . '</span>';
                                            $html .= '</div>';
                                        }
                                        $html .= '</div>';
                                        return $html;
                                    })
                                    ->extraAttributes(['class' => 'mt-4']),
                            ])
                            ->columnSpanFull(),
                    ]),

                Section::make('Additional Information')
                    ->schema([
                        TextInput::make('notes')
                            ->label('Notes')
                            ->columnSpanFull()
                            ->maxLength(1000)
                            ->nullable(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->where('sale_type', 'installment'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Sold At')
                    ->date('d-m-Y')
                    ->sortable(),
                TextColumn::make('next_payment_date')
                    ->label('Next Payment Date')
                    ->formatStateUsing(fn ($state, $record) => $record->next_payment_date),
                TextColumn::make('client.name')
                    ->label('Client')
                    ->searchable(),
                TextColumn::make('final_price')
                    ->label('Total Amount')
                    ->money('EGP')
                    ->sortable(),
                TextColumn::make('down_payment')
                    ->label('Down Payment')
                    ->money('EGP'),
                TextColumn::make('paid_amount')
                    ->label('Paid')
                    ->money('EGP'),
                TextColumn::make('remaining_amount')
                    ->label('Remaining')
                    ->money('EGP'),
                TextColumn::make('months_count')
                    ->label('Months')
                    ->formatStateUsing(function ($state, $record) {
                        $paidMonths = $record->payment_amounts ? count($record->payment_amounts) : 0;
                        return "{$state} ({$paidMonths} paid)";
                    }),
                TextColumn::make('monthly_installment')
                    ->label('Monthly')
                    ->money('EGP'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'ongoing' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'ongoing' => 'Ongoing',
                        'completed' => 'Completed',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (Sale $record, $action) {
                        $action->data['cachedItems'] = $record->items()->with('product')->get()->all();
                    })
                    ->after(function (Sale $record, $action) {
                        foreach ($action->data['cachedItems'] ?? [] as $item) {
                            if ($item->product) {
                                $item->product->increment('stock', $item->quantity);
                            }
                        }
                    }),
                Tables\Actions\Action::make('view_items')
                    ->icon('heroicon-o-eye')
                    ->modalContent(function (Sale $record) {
                        return view('filament.sale-items', [
                            'items' => $record->items()->with('product')->get()
                        ]);
                    })
                    ->modalHeading('Sale Items'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(function ($records, $action) {
                            $action->data['cachedItems'] = [];
                            foreach ($records as $record) {
                                foreach ($record->items()->with('product')->get() as $item) {
                                    $action->data['cachedItems'][] = $item;
                                }
                            }
                        })
                        ->after(function ($records, $action) {
                            foreach ($action->data['cachedItems'] ?? [] as $item) {
                                if ($item->product) {
                                    $item->product->increment('stock', $item->quantity);
                                }
                            }
                        }),
                ]),
            ]);
    }

    protected static function updateTotals(Get $get, Set $set): void
    {
        $items = collect($get('items'))->filter(fn ($item) => $item['product_id'] ?? false);
        $subtotal = $items->sum(fn ($item) => ($item['unit_price'] ?? 0) * ($item['quantity'] ?? 0));
        $discountType = $get('discount_type') ?? 'fixed';
        $discountValue = floatval($get('discount_value') ?? 0);

        if ($discountType === 'percent') {
            $discount = $subtotal * ($discountValue / 100);
        } else {
            $discount = $discountValue;
        }

        $finalPrice = $subtotal - $discount;
        $set('total_price', $subtotal);
        $set('discount', $discount); // Save only the value!
        $set('final_price', $finalPrice);
        self::updateInstallmentCalculations($get, $set);
    }

    protected static function updateInstallmentCalculations(Get $get, Set $set): void
    {
        $finalPrice = floatval($get('final_price') ?? 0);
        $downPayment = floatval($get('down_payment') ?? 0);

        $remainingAmount = max(0, $finalPrice - $downPayment);
        $interestRate = floatval($get('interest_rate') ?? 0);
        $months = intval($get('months_count') ?? 1);

        // Calculate interest on remaining amount after down payment
        $interestAmount = $remainingAmount * ($interestRate / 100);
        $monthlyInstallment = $months > 0 ? ($remainingAmount + $interestAmount) / $months : 0;

        $set('interest_amount', $interestAmount);
        $set('monthly_installment', $monthlyInstallment);
        $set('remaining_amount', $remainingAmount + $interestAmount);
        $set('paid_amount', $downPayment);
    }

    protected static function updateInstallmentFromMonthly(Get $get, Set $set): void
    {
        $finalPrice = $get('final_price') ?? 0;
        $downPayment = $get('down_payment') ?? 0;
        $remainingAmount = max(0, $finalPrice - $downPayment);
        $months = $get('months_count') ?? 1;
        $monthlyInstallment = $get('monthly_installment') ?? 0;

        if ($months > 0 && $remainingAmount > 0) {
            $totalToPay = $monthlyInstallment * $months;
            $interestAmount = $totalToPay - $remainingAmount;
            $interestRate = $remainingAmount > 0 ? ($interestAmount / $remainingAmount) * 100 : 0;
        } else {
            $interestAmount = 0;
            $interestRate = 0;
        }

        $set('interest_amount', $interestAmount);
        $set('interest_rate', $interestRate);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\InstallmentSaleResource\Pages\ListInstallmentSales::route('/'),
            'create' => \App\Filament\Resources\InstallmentSaleResource\Pages\CreateInstallmentSale::route('/create'),
            'edit' => \App\Filament\Resources\InstallmentSaleResource\Pages\EditInstallmentSale::route('/{record}/edit'),
        ];
    }
}