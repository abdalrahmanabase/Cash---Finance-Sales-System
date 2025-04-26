<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CashSaleResource\Pages;
use App\Filament\Resources\InstallmentSaleResource\Pages as InstallmentPages;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Inventory;
use App\Models\InventoryHistory;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Form;
use Filament\Forms\Components\Hidden;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Navigation\NavigationItem;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart'; // Changed icon
    protected static ?string $navigationGroup = 'Sales Management';
    protected static ?string $navigationLabel = 'Sales';
    protected static bool $shouldRegisterNavigation = true; // Changed to true

    public static function getNavigationItems(): array
    {
        return [
            NavigationItem::make('Cash Sales')
                ->url(static::getUrl('cash.index'))
                ->icon('heroicon-o-currency-dollar')
                ->isActiveWhen(fn (): bool => request()->routeIs(static::getRouteBaseName() . '.cash.*')),

            NavigationItem::make('Installment Sales')
                ->url(static::getUrl('installment.index'))
                ->icon('heroicon-o-credit-card')
                ->isActiveWhen(fn (): bool => request()->routeIs(static::getRouteBaseName() . '.installment.*')),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form;
    }

    public static function table(Table $table): Table
    {
        return $table;
    }

    public static function getPages(): array
    {
        return [
            // Cash Sales
            'cash.index' => Pages\ListCashSales::route('/sales/cash'),
            'cash.create' => Pages\CreateCashSale::route('/sales/cash/create'),
            'cash.edit' => Pages\EditCashSale::route('/sales/cash/{record}/edit'),
            
            // Installment Sales
            'installment.index' => InstallmentPages\ListInstallmentSales::route('/sales/installment'),
            'installment.create' => InstallmentPages\CreateInstallmentSale::route('/sales/installment/create'),
            'installment.edit' => InstallmentPages\EditInstallmentSale::route('/sales/installment/{record}/edit'),
        ];
    }
}

class CashSaleResource extends Resource
{
    protected static ?string $model = Sale::class;
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $modelLabel = 'Cash Sale';
    protected static ?string $navigationGroup = 'Sales Management';
    protected static ?string $navigationParentItem = 'Sales';
    protected static ?string $slug = 'sales/cash';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Sale Information')
                    ->schema([
                        Hidden::make('sale_type')->default('cash'),
                        Hidden::make('status')->default('completed'),

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
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        $product = Product::find($state);
                                        if ($product) {
                                            $set('unit_price', $product->cash_price);
                                            $set('available_stock', $product->inventory->stock ?? 0);
                                        }
                                    })
                                    ->required(),

                                TextInput::make('quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        $quantity = $get('quantity');
                                        $unitPrice = $get('unit_price');
                                        $set('total', $quantity * $unitPrice);
                                        self::validateStock($get, $set);
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
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                self::updateTotals($get, $set);
                            })
                            ->columnSpanFull(),

                        Hidden::make('total_price')->dehydrated(),

                        Select::make('discount_type')
                            ->label('Discount Type')
                            ->options([
                                'fixed' => 'Fixed (EGP)',
                                'percent' => 'Percent (%)',
                            ])
                            ->default('fixed')
                            ->live(),

                        TextInput::make('discount_value')
                            ->label('Discount')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                self::updateTotals($get, $set);
                            })
                            ->rules([
                                function (Get $get) {
                                    return function (string $attribute, $value, $fail) use ($get) {
                                        if ($get('discount_type') === 'percent' && $value > 100) {
                                            $fail('The discount percentage cannot exceed 100%.');
                                        }
                                    };
                                },
                            ]),

                        TextInput::make('final_price')
                            ->label('Final Price')
                            ->numeric()
                            ->prefix('EGP')
                            ->disabled()
                            ->dehydrated(),
                    ])
                    ->columns(2),

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
            ->modifyQueryUsing(fn ($query) => $query->where('sale_type', 'cash'))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('final_price')
                    ->label('Amount')
                    ->money('EGP')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn () => 'Completed'),
            ])
            ->filters([
                // Cash sale specific filters
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('view_items')
                    ->icon('heroicon-o-eye')
                    ->modalContent(function (Sale $record) {
                        $items = $record->items()->with('product')->get();
                        return view('filament.sale-items', ['items' => $items]);
                    })
                    ->modalHeading('Sale Items'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
    public static function afterCreate(Sale $sale): void
    {
        $sale->deductStock();
    }
    
    public static function afterUpdate(Sale $sale): void
    {
        $sale->deductStock();
    }

    // public static function afterCreate(Sale $sale): void
    // {
    //     $sale->deductStock(); $sale->load('items.product.inventory');
    //     foreach ($sale->items as $item) {
    //         $product = $item->product;
    //         $inventory = $product->inventory;
    //         $before = $inventory->stock;

    //         $inventory->decrement('stock', $item->quantity);

    //         InventoryHistory::create([
    //             'product_id' => $product->id,
    //             'inventory_id' => $inventory->id,
    //             'quantity' => -$item->quantity,
    //             'operation' => 'sale',
    //             'notes' => 'Cash sale',
    //             'previous_stock' => $before,
    //             'new_stock' => $before - $item->quantity,
    //         ]);
    //     }
    // }

    
    //     public static function afterUpdate(Sale $sale): void
    //     {
    //         $sale->deductStock(); $sale->load('items.product.inventory');
        
    //         foreach ($sale->items as $item) {
    //             $product = $item->product;
    //             $inventory = $product->inventory;
    //             $quantitySold = $item->quantity;
        
    //             $previousStock = $inventory->stock;
    //             $newStock = $previousStock - $quantitySold;
        
    //             $inventory->decrement('stock', $quantitySold);
        
    //             InventoryHistory::create([
    //                 'product_id' => $product->id,
    //                 'inventory_id' => $inventory->id,
    //                 'quantity' => -$quantitySold,
    //                 'operation' => 'sale_update',
    //                 'notes' => 'Updated sale',
    //                 'previous_stock' => $previousStock,
    //                 'new_stock' => $newStock,
    //             ]);
    //         }
    //     }
        
    

        protected static function updateTotals(Get $get, Set $set): void
        {
            $items = collect($get('items'))->filter(fn ($item) => $item['product_id'] ?? false);
            $subtotal = $items->sum(fn ($item) => ($item['unit_price'] ?? 0) * ($item['quantity'] ?? 0));
            $set('total_price', $subtotal);
    
            $discount = 0;
            $discountType = $get('discount_type');
            $discountValue = $get('discount_value') ?? 0;
    
            if ($discountType === 'percent') {
                $discount = $subtotal * ($discountValue / 100);
            } else {
                $discount = $discountValue;
            }
    
            $set('final_price', $subtotal - $discount);
        }

    protected static function validateStock(Get $get, Set $set): void
    {
        $quantity = $get('quantity');
        $availableStock = $get('available_stock');
        
        if ($quantity > $availableStock) {
            $set('quantity', $availableStock);
            $set('total', $availableStock * $get('unit_price'));
            
            Notification::make()
                ->title('Insufficient Stock')
                ->body("Only {$availableStock} items available in stock")
                ->danger()
                ->send();
        }
    }

    public static function getPages(): array
    {
        return [];
    }
}

class InstallmentSaleResource extends Resource
{
    protected static ?string $model = Sale::class;
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $modelLabel = 'Installment Sale';
    protected static ?string $navigationGroup = 'Sales Management';
    protected static ?string $navigationParentItem = 'Sales';
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
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('phone')
                                    ->required()
                                    ->tel()
                                    ->maxLength(20),
                                Forms\Components\TextInput::make('address')
                                    ->required()
                                    ->maxLength(500),
                            ])
                            ->createOptionAction(function (Forms\Components\Actions\Action $action) {
                                return $action
                                    ->modalHeading('Create Client')
                                    ->modalSubmitActionLabel('Create Client')
                                    ->modalWidth('lg');
                            })
                            ->required(),

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
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        $product = Product::find($state);
                                        if ($product) {
                                            $set('unit_price', $product->installment_price ?? $product->cash_price);
                                            $set('available_stock', $product->inventory->stock ?? 0);
                                        }
                                    })
                                    ->required(),

                                TextInput::make('quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, Get $get) {
                                        $quantity = $get('quantity');
                                        $unitPrice = $get('unit_price');
                                        $set('total', $quantity * $unitPrice);
                                        self::validateStock($get, $set);
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
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                self::updateTotals($get, $set);
                            })
                            ->columnSpanFull(),

                        Hidden::make('total_price')->dehydrated(),

                        Select::make('discount_type')
                            ->label('Discount Type')
                            ->options([
                                'fixed' => 'Fixed (EGP)',
                                'percent' => 'Percent (%)',
                            ])
                            ->default('fixed')
                            ->live(),

                        TextInput::make('discount_value')
                            ->label('Discount')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                self::updateTotals($get, $set);
                            })
                            ->rules([
                                function (Get $get) {
                                    return function (string $attribute, $value, $fail) use ($get) {
                                        if ($get('discount_type') === 'percent' && $value > 100) {
                                            $fail('The discount percentage cannot exceed 100%.');
                                        }
                                    };
                                },
                            ]),

                        TextInput::make('final_price')
                            ->label('Final Price')
                            ->numeric()
                            ->prefix('EGP')
                            ->disabled()
                            ->dehydrated(),

                        // Installment specific fields
                        Section::make('Installment Plan')
                            ->schema([
                                TextInput::make('interest_rate')
                                    ->label('Interest Rate')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->default(5)
                                    ->suffix('%')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        $finalPrice = $get('final_price');
                                        $interestRate = $get('interest_rate');
                                        $interestAmount = $finalPrice * ($interestRate / 100);
                                        $set('interest_amount', $interestAmount);
                                        self::calculateMonthlyInstallment($get, $set);
                                    })
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
                                    ->maxValue(36)
                                    ->default(12)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        self::calculateMonthlyInstallment($get, $set);
                                    })
                                    ->required(),

                                TextInput::make('monthly_installment')
                                    ->label('Monthly Installment')
                                    ->numeric()
                                    ->prefix('EGP')
                                    ->disabled()
                                    ->dehydrated(),

                                TextInput::make('down_payment')
                                    ->label('Down Payment')
                                    ->numeric()
                                    ->prefix('EGP')
                                    ->default(0)
                                    ->minValue(0)
                                    ->live(onBlur: true),
                            ])
                            ->columns(2),
                    ])
                    ->columns(2),

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
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('client.name')
                    ->label('Client')
                    ->searchable(),

                TextColumn::make('final_price')
                    ->label('Amount')
                    ->money('EGP')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'ongoing' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('monthly_installment')
                    ->label('Monthly Payment')
                    ->money('EGP'),
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
                Tables\Actions\Action::make('complete')
                    ->visible(fn (Sale $record): bool => $record->status === 'ongoing')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function (Sale $record) {
                        $record->update(['status' => 'completed']);
                    }),
                Tables\Actions\Action::make('view_items')
                    ->icon('heroicon-o-eye')
                    ->modalContent(function (Sale $record) {
                        $items = $record->items()->with('product')->get();
                        return view('filament.sale-items', ['items' => $items]);
                    })
                    ->modalHeading('Sale Items'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

  // In both resource classes, update the afterCreate and afterUpdate methods:

  public static function afterCreate(Sale $sale): void
{
    \DB::afterCommit(function () use ($sale) {
        $sale->deductStock();
    });
}

public static function afterUpdate(Sale $sale): void
{
    \DB::afterCommit(function () use ($sale) {
        $sale->deductStock();
    });
}

protected static function updateInventory(Sale $sale): void
{
    foreach ($sale->items as $item) {
        $product = $item->product;
        if ($product && $product->inventory) {
            $product->inventory->decrement('stock', $item->quantity);
            
            // Record inventory history
            $product->inventory->history()->create([
                'action' => 'sale',
                'quantity' => $item->quantity,
                'notes' => "Sold in {$sale->sale_type} sale #{$sale->id}",
                'related_id' => $sale->id,
                'related_type' => Sale::class
            ]);
        }
    }
}
    protected static function updateTotals(Get $get, Set $set): void
    {
        $items = collect($get('items'))->filter(fn ($item) => !empty($item['product_id']));
        $subtotal = $items->sum(fn ($item) => ($item['unit_price'] ?? 0) * ($item['quantity'] ?? 0));
        $set('total_price', $subtotal);

        $discountType = $get('discount_type');
        $discountValue = $get('discount_value') ?? 0;
        $discount = $discountType === 'percent' ? ($subtotal * $discountValue / 100) : $discountValue;

        $finalPrice = $subtotal - $discount;
        $set('final_price', $finalPrice);

        // Update installment calculations
        $interestRate = $get('interest_rate') ?? 0;
        $interestAmount = $finalPrice * ($interestRate / 100);
        $set('interest_amount', $interestAmount);
        self::calculateMonthlyInstallment($get, $set);
    }

    protected static function calculateMonthlyInstallment(Get $get, Set $set): void
    {
        $finalPrice = $get('final_price') ?? 0;
        $interestAmount = $get('interest_amount') ?? 0;
        $months = $get('months_count') ?? 1;

        $totalWithInterest = $finalPrice + $interestAmount;
        $monthlyInstallment = $months > 0 ? $totalWithInterest / $months : 0;

        $set('monthly_installment', $monthlyInstallment);
    }

    protected static function validateStock(Get $get, Set $set): void
    {
        $quantity = $get('quantity');
        $availableStock = $get('available_stock');
        
        if ($quantity > $availableStock) {
            $set('quantity', $availableStock);
            $set('total', $availableStock * $get('unit_price'));
            
            Notification::make()
                ->title('Insufficient Stock')
                ->body("Only {$availableStock} items available in stock")
                ->danger()
                ->send();
        }
    }

    public static function getPages(): array
    {
        return [];
    }
}