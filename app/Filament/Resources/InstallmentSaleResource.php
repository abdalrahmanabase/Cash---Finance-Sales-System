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
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Fieldset;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;

class InstallmentSaleResource extends Resource
{
    protected static ?string $model = Sale::class;
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    
    protected static ?string $slug = 'sales/installment';

    public static function getNavigationLabel(): string
    {
        return __('Installment Sales');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Sales Management');
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // ────────────────────────
                // Sale Information
                // ────────────────────────
                Section::make('Sale Information')
                    ->schema([
                        Hidden::make('sale_type')->default('installment'),
                        Hidden::make('status')->default('ongoing'),

                        // Client dropdown
                        Select::make('client_id')
                            ->label('Client')
                            ->relationship('client', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->suffixAction(
                                fn() => \Filament\Forms\Components\Actions\Action::make('createClient')
                                    ->label('Create Client')
                                    ->icon('heroicon-o-plus')
                                    ->url(route('filament.admin.resources.clients.create'))
                            ),

                        // Repeater saves directly to sale_items via relationship()
                        Repeater::make('items')
                            ->relationship()                // tells Filament “Sale→items relationship”
                            ->minItems(1)
                            ->required()
                            ->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->relationship('product', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateHydrated(function ($state, Set $set) {
                                        if (!$state) return;
                                        $product = Product::find($state);
                                        $set('available_stock', $product?->stock ?? 0);
                                    })
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        if (!$state) return;
                                        $product = Product::find($state);
                                        $set('unit_price', $product?->cash_price ?? 0);
                                        $set('available_stock', $product?->stock ?? 0);
                                    })
                                    ->required(),

                                TextInput::make('quantity')
                                    ->label('Quantity')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(1)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        $qty = $get('quantity');
                                        $prodId = $get('product_id');
                                        $up = $get('unit_price');
                                        if ($prodId) {
                                            $product = Product::find($prodId);
                                            if ($product && $qty > $product->stock) {
                                                Notification::make()
                                                    ->title('Insufficient stock')
                                                    ->body("Only {$product->stock} available")
                                                    ->danger()
                                                    ->send();
                                                $qty = $product->stock;
                                                $set('quantity', $qty);
                                            }
                                        }
                                        $set('total', $qty * $up);
                                        InstallmentSaleResource::updateTotals($get, $set);
                                    })
                                    ->required(),

                                TextInput::make('unit_price')
                                    ->label('Unit Price')
                                    ->numeric()
                                    ->prefix('جم')
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
                                    ->prefix('جم')
                                    ->disabled()
                                    ->dehydrated()
                                    ->afterStateHydrated(function (Set $set, Get $get) {
                                        $qty = $get('quantity') ?? 0;
                                        $up  = $get('unit_price') ?? 0;
                                        $set('total', $qty * $up);
                                    }),
                            ])
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => InstallmentSaleResource::updateTotals($get, $set))
                            ->afterStateHydrated(function (Get $get, Set $set, $state) {
                                foreach ($state as $index => $item) {
                                    $qty = $item['quantity'] ?? 0;
                                    $up  = $item['unit_price'] ?? 0;
                                    $set("items.{$index}.total", $qty * $up);
                                }
                            })
                            ->columnSpanFull(),

                        Hidden::make('total_price')->dehydrated(),

                        Select::make('discount_type')
                            ->label('Discount Type')
                            ->options([
                                'fixed'   => 'Fixed (جم)',
                                'percent' => 'Percent (%)',
                            ])
                            ->default('fixed')
                            ->dehydrated(false)
                            ->live()
                            ->afterStateHydrated(function (Set $set, $state, $record) {
                                if (!$record) return;
                                $subtotal = $record->total_price ?? 0;
                                $discount = $record->discount ?? 0;
                                if ($subtotal > 0 && $discount > 0) {
                                    $pct = round(($discount / $subtotal) * 100, 2);
                                    if (abs($discount - ($subtotal * ($pct / 100))) < 0.01) {
                                        $set('discount_type', 'percent');
                                        return;
                                    }
                                }
                                $set('discount_type', 'fixed');
                            }),

                        TextInput::make('discount_value')
                            ->label('Discount')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->live(onBlur: true)
                            ->afterStateHydrated(function (Set $set, $state, $record) {
                                if (!$record) return;
                                $subtotal = $record->total_price ?? 0;
                                $discount = $record->discount ?? 0;
                                if ($subtotal > 0 && $discount > 0) {
                                    $pct = round(($discount / $subtotal) * 100, 2);
                                    if (abs($discount - ($subtotal * ($pct / 100))) < 0.01) {
                                        $set('discount_value', $pct);
                                        return;
                                    }
                                }
                                $set('discount_value', $discount);
                            })
                            ->afterStateUpdated(fn (Get $get, Set $set) => InstallmentSaleResource::updateTotals($get, $set)),

                        Hidden::make('discount')->dehydrated(true),

                        TextInput::make('final_price')
                            ->label('Final Price')
                            ->numeric()
                            ->prefix('جم')
                            ->disabled()
                            ->dehydrated(),

                        Hidden::make('remaining_amount')->dehydrated(),
                    ])
                    ->columns(2),


                // ────────────────────────
                // Installment Plan
                // ────────────────────────
                Section::make('Installment Plan')
                    ->schema([
                        TextInput::make('down_payment')
                            ->label('Down Payment')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->live(onBlur: true)
                            ->dehydrated(true)
                            ->afterStateHydrated(function (Set $set, $state) {
                                $set('down_payment', floatval($state ?? 0));
                            })
                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                $downPayment = floatval($state);
                                $finalPrice  = floatval($get('final_price') ?? 0);
                                if ($downPayment > $finalPrice) {
                                    Notification::make()
                                        ->title('Invalid down payment')
                                        ->body('Down payment cannot exceed the final price')
                                        ->danger()
                                        ->send();
                                    $downPayment = $finalPrice;
                                }
                                $set('down_payment', $downPayment);
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
                            ->prefix('جم')
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
                            ->step(1)                    // ← only whole steps
                            ->rules(['integer'])         // ← validate integer
                            ->prefix('جم')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => InstallmentSaleResource::updateInstallmentFromMonthly($get, $set))
                            ->required(),

                        TextInput::make('preferred_payment_day')
                            ->label('Preferred Payment Day')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(31)
                            ->helperText('Day of month (e.g. 5 → pay every month on the 5th)')
                            ->nullable(),

                        Hidden::make('payment_dates')->default([]),
                        Hidden::make('payment_amounts')->default([]),

                        // We removed any “Hidden::make('next_payment_date')->afterState…” from here.
                        // The model itself (in Sale::saving) will compute & persist next_payment_date.
                    ])
                    ->columns(2),


                // ────────────────────────
                // Payment Summary / History (read-only)
                // ────────────────────────
                Section::make('Payment Summary')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                Placeholder::make('paid_amount')
                                    ->label('Paid Amount')
                                    ->content(fn ($record) => $record?->paid_amount
                                        ? 'جم ' . number_format($record->paid_amount, 2)
                                        : 'جم 0.00'
                                    ),

                                Placeholder::make('remaining_amount')
                                    ->label('Remaining Amount')
                                    ->content(fn ($record) => $record?->remaining_amount
                                        ? 'جم ' . number_format($record->remaining_amount, 2)
                                        : 'جم 0.00'
                                    ),

                                Placeholder::make('down_payment')
                                    ->label('Down Payment')
                                    ->content(fn ($record) => $record?->down_payment
                                        ? 'جم ' . number_format($record->down_payment, 2)
                                        : 'جم 0.00'
                                    ),

                                Placeholder::make('months_progress')
                                    ->label('Months Progress')
                                    ->content(fn ($record) => $record
                                        ? $record->getPaymentScheduleProgress()['fully_paid_months']
                                          . '/' . $record->months_count
                                        : '0/0'
                                    ),
                            ]),

                        Fieldset::make('Payment History')
                            ->schema([
                                Placeholder::make('history')
                                    ->label('Payment History')
                                    ->content(function ($record) {
                                        if (!$record) {
                                            return 'No payments recorded yet.';
                                        }
                                        $payments = $record->all_payments;
                                        if (empty($payments)) {
                                            return 'No payments recorded yet.';
                                        }
                                        $html = '<div class="space-y-2">';
                                        foreach ($payments as $p) {
                                            $html .= '<div class="flex justify-between border-b pb-1">';
                                            $d    = Carbon::parse($p['date'])->format('d-m-Y');
                                            $amt  = number_format($p['amount'], 2);
                                            $html .= "<span>{$d}</span>";
                                            $html .= "<span class=\"font-medium\">جم {$amt}</span>";
                                            $html .= '</div>';
                                        }
                                        $html .= '</div>';
                                        return new \Illuminate\Support\HtmlString($html);
                                    })
                                    ->extraAttributes(['class' => 'mt-4'])
                                    ->columnSpanFull(),
                            ]),
                    ]),

                // ────────────────────────
                // Additional Information
                // ────────────────────────
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
            ->modifyQueryUsing(fn(Builder $query) => $query->where('sale_type', 'installment'))
            ->columns([
                 TextColumn::make('created_at')
                    ->label('Sold At')
                    ->date('d-m-Y')
                    ->sortable(),

                TextColumn::make('next_payment_date')
    ->label('Next Payment Date')
    ->formatStateUsing(fn($state, $record) =>
        $state
            ? $state->format('d-m-Y') // $state is Carbon instance or null
            : ($record->status === 'completed' ? 'Ended' : '—')
    )
    ->sortable(),


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
                    ->label('Months Paid')
                    ->formatStateUsing(fn($state, $record) => 
                        $record->getPaymentScheduleProgress()['fully_paid_months'] . "/{$state}"
                    ),

                TextColumn::make('monthly_installment')
                    ->label('Monthly')
                    ->money('EGP'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state) => match($state) {
                        'completed' => 'success',
                        'ongoing'   => 'warning',
                        default     => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'ongoing'   => 'Ongoing',
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
                    ->modalContent(fn(Sale $record) => view('filament.sale-items', [
                        'items' => $record->items()->with('product')->get()
                    ]))
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
        $items   = collect($get('items'))->filter(fn($item) => !empty($item['product_id']));
        $subtotal= $items->sum(fn($item) => ($item['unit_price'] ?? 0) * ($item['quantity'] ?? 0));
        $discType= $get('discount_type') ?? 'fixed';
        $discVal = floatval($get('discount_value') ?? 0);

        if ($discType === 'percent') {
            $discount = $subtotal * ($discVal / 100);
        } else {
            $discount = $discVal;
        }

        $finalPrice = $subtotal - $discount;
        $set('total_price', $subtotal);
        $set('discount', $discount);
        $set('final_price', $finalPrice);

        self::updateInstallmentCalculations($get, $set);
    }

    protected static function updateInstallmentCalculations(Get $get, Set $set): void
{
    $finalPrice = floatval($get('final_price') ?? 0);
    $down       = floatval($get('down_payment') ?? 0);
    $remainPri  = max(0, $finalPrice - $down);
    $irate      = floatval($get('interest_rate') ?? 0);
    $months     = max(1, intval($get('months_count') ?? 1));

    $interestAmt = $remainPri * ($irate / 100);
    $totalRem    = $remainPri + $interestAmt;

    // Round UP to the next integer:
    $monthlyInst = (int) ceil($totalRem / $months);

    $set('interest_amount', (int) round($interestAmt));
    $set('monthly_installment', $monthlyInst);
    $set('remaining_amount', (int) round($totalRem));
}


    protected static function updateInstallmentFromMonthly(Get $get, Set $set): void
    {
        $finalPrice = floatval($get('final_price') ?? 0);
        $down       = floatval($get('down_payment') ?? 0);
        $remainPri  = max(0, $finalPrice - $down);
        $months     = intval($get('months_count') ?? 1);
        $monthlyInst= floatval($get('monthly_installment') ?? 0);

        if ($months > 0 && $remainPri > 0) {
            $totalToPay    = $monthlyInst * $months;
            $interestAmt   = $totalToPay - $remainPri;
            $interestRate  = $remainPri > 0 ? ($interestAmt / $remainPri) * 100 : 0;
        } else {
            $interestAmt  = 0;
            $interestRate = 0;
        }

        $set('interest_amount', $interestAmt);
        $set('interest_rate', $interestRate);
        $set('remaining_amount', $remainPri + $interestAmt);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('sale_type', 'installment');
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\InstallmentSaleResource\Pages\ListInstallmentSales::route('/'),
            'create'=> \App\Filament\Resources\InstallmentSaleResource\Pages\CreateInstallmentSale::route('/create'),
            'edit'  => \App\Filament\Resources\InstallmentSaleResource\Pages\EditInstallmentSale::route('/{record}/edit'),
        ];
    }
}
