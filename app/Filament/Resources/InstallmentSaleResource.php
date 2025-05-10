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
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Tables\Columns\TextColumn;

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
                        self::clientField(),
                        self::itemsRepeater(),
                        ...self::pricingFields(),
                    ])
                    ->columns(2),
                self::installmentPlanSection(),
                self::additionalInformationSection(),
            ]);
    }

    protected static function clientField(): Select
    {
        return Select::make('client_id')
            ->label('Client')
            ->relationship('client', 'name')
            ->searchable()
            ->preload()
            ->required()
            ->suffixAction(
                \Filament\Forms\Components\Actions\Action::make('addClient')
                    ->icon('heroicon-o-plus')
                    ->url(route('filament.admin.resources.clients.create'))
                    ->tooltip('Add new client')
            );
    }

    protected static function itemsRepeater(): Repeater
    {
        return Repeater::make('items')
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
                        self::updateTotals($get, $set);
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
            ->afterStateUpdated(fn (Get $get, Set $set) => self::updateTotals($get, $set))
            ->columnSpanFull();
    }

    protected static function pricingFields(): array
    {
        return [
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
                ->afterStateUpdated(fn (Get $get, Set $set) => self::updateTotals($get, $set))
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
        ];
    }

    protected static function installmentPlanSection(): Section
    {
        return Section::make('Installment Plan')
            ->schema([
                TextInput::make('down_payment')
                    ->label('Down Payment')
                    ->numeric()
                    ->prefix('EGP')
                    ->default(0)
                    ->minValue(0)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::updateInstallmentCalculations($get, $set)),

                TextInput::make('interest_rate')
                    ->label('Interest Rate')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(5)
                    ->suffix('%')
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::updateInstallmentCalculations($get, $set))
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
                    ->afterStateUpdated(fn (Get $get, Set $set) => self::updateInstallmentCalculations($get, $set))
                    ->required(),

                TextInput::make('monthly_installment')
                    ->label('Monthly Installment')
                    ->numeric()
                    ->prefix('EGP')
                    ->disabled()
                    ->dehydrated(),
            ])
            ->columns(2);
    }

    protected static function additionalInformationSection(): Section
    {
        return Section::make('Additional Information')
            ->schema([
                TextInput::make('notes')
                    ->label('Notes')
                    ->columnSpanFull()
                    ->maxLength(1000)
                    ->nullable(),
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
                Tables\Actions\DeleteAction::make()
                    ->before(function (Sale $record, $action) {
                        // Cache items before deletion
                        $action->data['cachedItems'] = $record->items()->with('product')->get()->all();
                    })
                    ->after(function (Sale $record, $action) {
                        // Restock using cached items
                        foreach ($action->data['cachedItems'] ?? [] as $item) {
                            if ($item->product) {
                                $item->product->increment('stock', $item->quantity);
                            }
                        }
                    }),
                Tables\Actions\Action::make('complete')
                    ->visible(fn (Sale $record): bool => $record->status === 'ongoing')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function (Sale $record) {
                        DB::transaction(function () use ($record) {
                            $record->update(['status' => 'completed']);
                            $record->deductStock();
                        });
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
                            // Cache all items for all records
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
        $discount = match ($get('discount_type')) {
            'percent' => $subtotal * ($get('discount_value') / 100),
            default => $get('discount_value') ?? 0
        };
        $finalPrice = $subtotal - $discount;
        $set('total_price', $subtotal);
        $set('final_price', $finalPrice);
        self::updateInstallmentCalculations($get, $set);
    }

    protected static function updateInstallmentCalculations(Get $get, Set $set): void
    {
        $finalPrice = $get('final_price') ?? 0;
        $downPayment = $get('down_payment') ?? 0;
        $remainingAmount = max(0, $finalPrice - $downPayment);
        $interestRate = $get('interest_rate') ?? 0;
        $months = $get('months_count') ?? 1;
        
        $interestAmount = $remainingAmount * ($interestRate / 100);
        $monthlyInstallment = $months > 0 ? ($remainingAmount + $interestAmount) / $months : 0;
        
        $set('interest_amount', $interestAmount);
        $set('monthly_installment', $monthlyInstallment);
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