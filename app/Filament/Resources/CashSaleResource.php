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

class CashSaleResource extends Resource
{
    protected static ?string $model = Sale::class;
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $slug = 'sales/cash';

    public static function getNavigationLabel(): string
    {
        return __('Cash Sales');
    }
    public static function getPluralModelLabel(): string
    {
        return __('Cash Sales');
    }
    public static function getModelLabel(): string
    {
        return __('Cash Sale');
    }
    public static function getTitle(): string
    {
        return __('Sales');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Sales Management');
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
                        Hidden::make('sale_type')->default('cash'),
                        Hidden::make('status')->default('completed'),
                        self::itemsRepeater(),
                        ...self::pricingFields(),
                    ])
                    ->columns(2),
                self::additionalInformationSection(),
            ]);
    }

    protected static function itemsRepeater(): Repeater
    {
        return Repeater::make('items')
            ->relationship()
            ->minItems(1)
            ->required()
            ->schema([
                Select::make('product_id')
                    ->label(__('Product'))
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateHydrated(function ($state, Set $set, $record) {
                        if (!$state) return;
                        $product = Product::find($state);
                        $set('available_stock', $product->stock ?? 0);
                        $set('total', ($record->unit_price ?? 0) * ($record->quantity ?? 0));
                    })
                    ->afterStateUpdated(function ($state, Set $set) {
                        if (!$state) return;
                        $product = Product::find($state);
                        $set('unit_price', $product->cash_price);
                        $set('available_stock', $product->stock ?? 0);
                    })
                    ->required(),

                TextInput::make('quantity')
                    ->label(__('Quantity'))
                    ->numeric()
                    ->default(0)
                    ->minValue(1)
                    ->live(onBlur: true)
                    ->afterStateHydrated(function (Set $set, $record) {
                        if ($record) {
                            $set('total', ($record->unit_price ?? 0) * ($record->quantity ?? 0));
                        }
                    })
                    ->afterStateUpdated(function (Set $set, Get $get) {
                        $quantity = $get('quantity');
                        $productId = $get('product_id');
                        $unitPrice = $get('unit_price');
                        if ($productId) {
                            $product = Product::find($productId);
                            if ($product && $quantity > $product->stock) {
                                Notification::make()
                                    ->title(__('Insufficient stock'))
                                    ->body(__('Only :stock available', ['stock' => $product->stock]))
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
                    ->dehydrated(),
            ])
            ->live()
            ->afterStateUpdated(fn (Get $get, Set $set) => self::updateTotals($get, $set))
            ->columnSpanFull();
    }

    protected static function pricingFields(): array
    {
        return [
            Hidden::make('total_price')
                ->dehydrated()
                ->afterStateHydrated(function (Set $set, $state) {
                    if ($state !== null) {
                        $set('total_price', $state);
                    }
                }),

            Select::make('discount_type')
                ->label(__('Discount Type'))
                ->options([
                    'fixed' => __('Fixed (:currency)', ['currency' => app()->getLocale() === 'ar' ? 'جم' : 'EGP']),
                    'percent' => __('Percent (%)'),
                ])
                ->default('fixed')
                ->dehydrated(false)
                ->live()
                ->afterStateHydrated(function (Set $set, $state, $record) {
                    if (!$record) return;
                    $subtotal = $record->total_price ?? 0;
                    $discount = $record->discount ?? 0;
                    if ($subtotal > 0 && $discount > 0) {
                        $percent = round(($discount / $subtotal) * 100, 2);
                        if (abs($discount - ($subtotal * ($percent / 100))) < 0.01) {
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
                    $discount = $record->discount ?? 0;
                    if ($subtotal > 0 && $discount > 0) {
                        $percent = round(($discount / $subtotal) * 100, 2);
                        if (abs($discount - ($subtotal * ($percent / 100))) < 0.01) {
                            $set('discount_value', $percent);
                            return;
                        }
                    }
                    $set('discount_value', $discount);
                })
                ->afterStateUpdated(fn (Get $get, Set $set) => self::updateTotals($get, $set)),

            Hidden::make('discount')->dehydrated(true),

            TextInput::make('final_price')
                ->label(__('Final Price'))
                ->numeric()
                ->prefix(app()->getLocale() === 'ar' ? 'جم' : 'EGP')
                ->disabled()
                ->dehydrated()
                ->afterStateHydrated(function (Set $set, $state) {
                    if ($state !== null) {
                        $set('final_price', $state);
                    }
                }),
        ];
    }

    protected static function additionalInformationSection(): Section
    {
        return Section::make(__('Additional Information'))
            ->schema([
                TextInput::make('notes')
                    ->label(__('Notes'))
                    ->columnSpanFull()
                    ->maxLength(1000)
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->where('sale_type', 'cash'))
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Date'))
                    ->date('d-m-Y')
                    ->sortable(),

                TextColumn::make('final_price')
                    ->label(__('Final Price'))
                    ->getStateUsing(fn ($record) => number_format($record->final_price, 2) . ' ' . static::getCurrencySymbol())
                    ->sortable(),
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
                    ->modalContent(function (Sale $record) {
                        return view('filament.sale-items', [
                            'items' => $record->items()->with('product')->get(),
                            'currencySymbol' => app()->getLocale() === 'ar' ? 'جم' : 'EGP',
                        ]);
                    })
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
        $items = collect($get('items'))->filter(fn ($item) => $item['product_id'] ?? false);
        $subtotal = $items->sum(fn ($item) => ($item['unit_price'] ?? 0) * ($item['quantity'] ?? 0));

        $discount = match ($get('discount_type')) {
            'percent' => $subtotal * (($get('discount_value') ?? 0) / 100),
            default => $get('discount_value') ?? 0
        };

        $set('total_price', $subtotal);
        $set('discount', $discount);
        $set('final_price', $subtotal - $discount);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\CashSaleResource\Pages\ListCashSales::route('/'),
            'create' => \App\Filament\Resources\CashSaleResource\Pages\CreateCashSale::route('/create'),
            'edit' => \App\Filament\Resources\CashSaleResource\Pages\EditCashSale::route('/{record}/edit'),
        ];
    }
}
