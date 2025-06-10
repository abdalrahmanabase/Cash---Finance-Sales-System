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

    public static function getModelLabel(): string
    {
        return __('Installment Sale');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Installment Sales');
    }

    protected static function getCurrencySymbol(): string
{
    return app()->getLocale() === 'ar' ? 'جم' : 'EGP';
}

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make(__('Sale Information'))
                    ->schema([
                        Hidden::make('sale_type')->default('installment'),
                        Hidden::make('status')->default('ongoing'),

                        Select::make('client_id')
                            ->label(__('Client'))
                            ->relationship('client', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->suffixAction(
                                fn() => \Filament\Forms\Components\Actions\Action::make('createClient')
                                    ->label(__('Create Client'))
                                    ->icon('heroicon-o-plus')
                                    ->url(route('filament.admin.resources.clients.create'))
                            ),

                        Repeater::make('items')
                            ->relationship()
                            ->label(__('Items'))
                            ->minItems(1)
                            ->required()
                            ->schema([
                                Select::make('product_id')
                                    ->label(__('Product'))
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
                                        $set('unit_price',       $product?->cash_price ?? 0);
                                        $set('available_stock',  $product?->stock ?? 0);
                                    })
                                    ->required(),

                                TextInput::make('quantity')
                                    ->label(__('Quantity'))
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(1)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        $qty    = $get('quantity');
                                        $prodId = $get('product_id');
                                        $up     = $get('unit_price');
                                        if ($prodId) {
                                            $product = Product::find($prodId);
                                            if ($product && $qty > $product->stock) {
                                                Notification::make()
                                                    ->title(__('Insufficient stock'))
                                                    ->body(__('Only :stock available', ['stock' => $product->stock]))
                                                    ->danger()
                                                    ->send();
                                                $qty = $product->stock;
                                                $set('quantity', $qty);
                                            }
                                        }
                                        $set('total', $qty * $up);
                                        static::updateTotals($get, $set);
                                    })
                                    ->required(),

                                TextInput::make('unit_price')
                                    ->label(__('Unit Price'))
                                    ->numeric()
                                    ->prefix(static::getCurrencySymbol())
                                    ->disabled()
                                    ->dehydrated(),

                                TextInput::make('available_stock')
                                    ->label(__('Available Stock'))
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('total')
                                    ->label(__('Item Total'))
                                    ->numeric()
                                    ->prefix(static::getCurrencySymbol())
                                    ->disabled()
                                    ->dehydrated()
                                    ->afterStateHydrated(function (Get $get, Set $set) {
                                        $qty = $get('quantity')   ?? 0;
                                        $up  = $get('unit_price') ?? 0;
                                        $set('total', $qty * $up);
                                    }),
                            ])
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => static::updateTotals($get, $set))
                            ->afterStateHydrated(function (Get $get, Set $set, $state) {
                                foreach ($state as $index => $item) {
                                    $qty = $item['quantity']   ?? 0;
                                    $up  = $item['unit_price'] ?? 0;
                                    $set("items.{$index}.total", $qty * $up);
                                }
                            })
                            ->columnSpanFull(),

                        Hidden::make('total_price')->dehydrated(),

                        Select::make('discount_type')
                            ->label(__('Discount Type'))
                            ->options([
                                'fixed'   => __('Fixed (EGP)'),
                                'percent' => __('Percent (%)'),
                            ])
                            ->default('fixed')
                            ->dehydrated(false)
                            ->live()
                            ->afterStateHydrated(function (Set $set, $state, $record) {
                                if (!$record) return;
                                $subtotal = $record->total_price  ?? 0;
                                $discount = $record->discount     ?? 0;
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
                            ->label(__('Discount'))
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->live(onBlur: true)
                            ->afterStateHydrated(function (Set $set, $state, $record) {
                                if (!$record) return;
                                $subtotal = $record->total_price ?? 0;
                                $discount = $record->discount    ?? 0;
                                if ($subtotal > 0 && $discount > 0) {
                                    $pct = round(($discount / $subtotal) * 100, 2);
                                    if (abs($discount - ($subtotal * ($pct / 100))) < 0.01) {
                                        $set('discount_value', $pct);
                                        return;
                                    }
                                }
                                $set('discount_value', $discount);
                            })
                            ->afterStateUpdated(fn (Get $get, Set $set) => static::updateTotals($get, $set)),

                        Hidden::make('discount')->dehydrated(true),

                        TextInput::make('final_price')
                            ->label(__('Final Price'))
                            ->numeric()
                            ->prefix(static::getCurrencySymbol())
                            ->disabled()
                            ->dehydrated(),

                        Hidden::make('remaining_amount')->dehydrated(),
                    ])
                    ->columns(2),

                Section::make(__('Installment Plan'))
                    ->schema([
                        TextInput::make('down_payment')
                            ->label(__('Down Payment'))
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->live(onBlur: true)
                            ->dehydrated(true)
                            ->afterStateHydrated(fn (Set $set, $state) => $set('down_payment', floatval($state ?? 0)))
                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                $downPayment = floatval($state);
                                $finalPrice  = floatval($get('final_price') ?? 0);
                                if ($downPayment > $finalPrice) {
                                    Notification::make()
                                        ->title(__('Invalid down payment'))
                                        ->body(__('Down payment cannot exceed the final price'))
                                        ->danger()
                                        ->send();
                                    $downPayment = $finalPrice;
                                }
                                $set('down_payment', $downPayment);
                                static::updateInstallmentCalculations($get, $set);
                            }),

                        TextInput::make('interest_rate')
                            ->label(__('Interest Rate'))
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(1000)
                            ->default(0)
                            ->suffix('%')
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => static::updateInstallmentCalculations($get, $set))
                            ->required(),

                        TextInput::make('interest_amount')
                            ->label(__('Interest Amount'))
                            ->numeric()
                            ->prefix(static::getCurrencySymbol())
                            ->disabled()
                            ->dehydrated(),

                        TextInput::make('months_count')
                            ->label(__('Installment Months'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->default(12)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => static::updateInstallmentCalculations($get, $set))
                            ->required(),

                        TextInput::make('monthly_installment')
                            ->label(__('Monthly Installment'))
                            ->numeric()
                            ->step(1)
                            ->rules(['integer'])
                            ->prefix(static::getCurrencySymbol())
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Get $get, Set $set) => static::updateInstallmentFromMonthly($get, $set))
                            ->required(),

                        TextInput::make('preferred_payment_day')
                            ->label(__('Preferred Payment Day'))
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(31)
                            ->helperText(__('Day of month (e.g. 5 → pay every month on the 5th)'))
                            ->nullable(),

                        Hidden::make('payment_dates')->default([]),
                        Hidden::make('payment_amounts')->default([]),
                    ])
                    ->columns(2),

                Section::make(__('Payment Summary'))
                    
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                Placeholder::make('paid_amount')
                                    ->label(__('Paid Amount'))
                                    ->content(fn ($record) => $record?->paid_amount
                                        ? __('EGP') . ' ' . number_format($record->paid_amount, 2)
                                        : __('EGP') . ' 0.00'
                                    ),

                                Placeholder::make('remaining_amount')
                                    ->label(__('Remaining Amount'))
                                    ->content(fn ($record) => $record?->remaining_amount
                                        ? __('EGP') . ' ' . number_format($record->remaining_amount, 2)
                                        : __('EGP') . ' 0.00'
                                    ),

                                Placeholder::make('down_payment')
                                    ->label(__('Down Payment'))
                                    ->content(fn ($record) => $record?->down_payment
                                        ? __('EGP') . ' ' . number_format($record->down_payment, 2)
                                        : __('EGP') . ' 0.00'
                                    ),

                                Placeholder::make('months_progress')
                                    ->label(__('Months Progress'))
                                    ->content(fn ($record) => $record
                                        ? $record->getPaymentScheduleProgress()['fully_paid_months']
                                          . '/' . $record->months_count
                                        : '0/0'
                                    ),
                            ]),

                        Fieldset::make(__('Payment History'))
                            ->schema([
                                Placeholder::make('history')
                                    ->label(__('Payment History'))
                                    ->content(function ($record) {
                                        if (!$record) {
                                            return __('No payments recorded yet.');
                                        }
                                        $payments = $record->all_payments;
                                        if (empty($payments)) {
                                            return __('No payments recorded yet.');
                                        }
                                        $html = '<div class="space-y-2">';
                                        foreach ($payments as $p) {
                                            $d    = Carbon::parse($p['date'])->format('d-m-Y');
                                            $amt  = number_format($p['amount'], 2);
                                            $html .= "<div class='flex justify-between border-b pb-1'>";
                                            $html .= "<span>{$d}</span>";
                                            $html .= "<span class='font-medium'>" . static::getCurrencySymbol() . " {$amt}</span>";
                                            $html .= '</div>';
                                        }
                                        $html .= '</div>';
                                        return new \Illuminate\Support\HtmlString($html);
                                    })
                                    ->extraAttributes(['class' => 'mt-4'])
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Section::make(__('Additional Information'))
                    ->schema([
                        TextInput::make('notes')
                            ->label(__('Notes'))
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
                    ->label(__('Sold At'))
                    ->date('d-m-Y')
                    ->sortable(),

                TextColumn::make('next_payment_date')
                    ->label(__('Next Payment Date'))
                    ->formatStateUsing(fn ($state, $record) =>
                        $state
                            ? $state->format('d-m-Y')
                            : ($record->status === 'completed' ? __('Ended') : '—')
                    )
                    ->sortable(),

                TextColumn::make('client.name')
                    ->label(__('Client'))
                    ->searchable(),

                TextColumn::make('final_price')
                    ->label(__('Total Amount'))
                    ->getStateUsing(fn ($record) => number_format($record->final_price, 2) . ' ' . static::getCurrencySymbol())
                    ->sortable(),

                TextColumn::make('down_payment')
                    ->label(__('Down Payment'))
                    ->getStateUsing(fn ($record) => number_format($record->down_payment, 2) . ' ' . static::getCurrencySymbol())
                    ->sortable(),

                TextColumn::make('paid_amount')
                    ->label(__('Paid'))
                    ->getStateUsing(fn ($record) => number_format($record->paid_amount, 2) . ' ' . static::getCurrencySymbol()),

                TextColumn::make('remaining_amount')
                    ->label(__('Remaining'))
                    ->getStateUsing(fn ($record) => number_format($record->remaining_amount, 2) . ' ' . static::getCurrencySymbol()),

                TextColumn::make('months_count')
                    ->label(__('Months Paid'))
                    ->formatStateUsing(fn ($state, $record) =>
                        $record->getPaymentScheduleProgress()['fully_paid_months']
                        . "/{$state}"
                    ),

                TextColumn::make('monthly_installment')
                    ->label(__('Monthly'))
                    ->getStateUsing(fn ($record) => number_format($record->monthly_installment, 2) . ' ' . static::getCurrencySymbol()),

                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'completed' => 'success',
                        'ongoing'   => 'warning',
                        default     => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'completed' => __('Completed'),
                        'ongoing'   => __('Ongoing'),
                        default     => __('Unknown'),
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'ongoing'   => __('Ongoing'),
                        'completed' => __('Completed'),
                    ])
                    ->label(__('Status')),
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
                    ->label(__('View Items'))
                    ->modalContent(fn (Sale $record) => view('filament.sale-items', [
                        'items' => $record->items()->with('product')->get(),
                        'currencySymbol' => app()->getLocale() === 'ar' ? 'جم' : 'EGP',
                    ]))
                    ->modalHeading(__('Sale Items')),
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
        $items    = collect($get('items'))->filter(fn ($item) => !empty($item['product_id']));
        $subtotal = $items->sum(fn ($item) => ($item['unit_price'] ?? 0) * ($item['quantity'] ?? 0));
        $discType = $get('discount_type')  ?? 'fixed';
        $discVal  = floatval($get('discount_value') ?? 0);

        $discount = $discType === 'percent'
            ? $subtotal * ($discVal / 100)
            : $discVal;

        $finalPrice = $subtotal - $discount;
        $set('total_price',      $subtotal);
        $set('discount',         $discount);
        $set('final_price',      $finalPrice);

        static::updateInstallmentCalculations($get, $set);
    }

    protected static function updateInstallmentCalculations(Get $get, Set $set): void
    {
        $finalPrice = floatval($get('final_price')      ?? 0);
        $down       = floatval($get('down_payment')     ?? 0);
        $remainPri  = max(0, $finalPrice - $down);
        $irate      = floatval($get('interest_rate')    ?? 0);
        $months     = max(1, intval($get('months_count')?? 1));

        $interestAmt = $remainPri * ($irate / 100);
        $totalRem    = $remainPri + $interestAmt;
        $monthlyInst = (int) ceil($totalRem / $months);

        $set('interest_amount',     (int) round($interestAmt));
        $set('monthly_installment', $monthlyInst);
        $set('remaining_amount',    (int) round($totalRem));
    }

    protected static function updateInstallmentFromMonthly(Get $get, Set $set): void
    {
        $finalPrice = floatval($get('final_price')      ?? 0);
        $down       = floatval($get('down_payment')     ?? 0);
        $remainPri  = max(0, $finalPrice - $down);
        $months     = intval($get('months_count')       ?? 1);
        $monthlyInst= floatval($get('monthly_installment') ?? 0);

        if ($months > 0 && $remainPri > 0) {
            $totalToPay   = $monthlyInst * $months;
            $interestAmt  = $totalToPay - $remainPri;
            $interestRate = $remainPri > 0
                ? ($interestAmt / $remainPri) * 100
                : 0;
        } else {
            $interestAmt  = 0;
            $interestRate = 0;
        }

        $set('interest_amount',    $interestAmt);
        $set('interest_rate',      $interestRate);
        $set('remaining_amount',   $remainPri + $interestAmt);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('sale_type', 'installment');
    }

    public static function getPages(): array
    {
        return [
            'index'  => \App\Filament\Resources\InstallmentSaleResource\Pages\ListInstallmentSales::route('/'),
            'create' => \App\Filament\Resources\InstallmentSaleResource\Pages\CreateInstallmentSale::route('/create'),
            'edit'   => \App\Filament\Resources\InstallmentSaleResource\Pages\EditInstallmentSale::route('/{record}/edit'),
        ];
    }
}
