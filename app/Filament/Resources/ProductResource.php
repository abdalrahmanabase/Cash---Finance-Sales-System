<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';

    public static function getNavigationLabel(): string
    {
        return __('Products');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Products Management');
    }

    public static function getModelLabel(): string
    {
        return __('Product');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Products');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make(__('Product Information'))->schema([
                TextInput::make('name')
                    ->label(__('Name'))
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(2),

                TextInput::make('code')
                    ->label(__('Code'))
                    ->unique(ignoreRecord: true)
                    ->nullable()
                    ->maxLength(255),

                Select::make('category_id')
                    ->label(__('Category'))
                    ->relationship('category', 'name')
                    ->required()
                    ->preload()
                    ->searchable(),

                Select::make('provider_id')
                    ->label(__('Provider'))
                    ->relationship('provider', 'name')
                    ->required()
                    ->preload()
                    ->searchable(),

                TextInput::make('stock')
                    ->label(__('Current Stock'))
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
            ])->columns(3),

            Section::make(__('Pricing'))->schema([
                TextInput::make('purchase_price')
                    ->label(__('Purchase Price'))
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->prefix(__('EGP'))
                    ->reactive()
                    ->lazy()  // ← only sync & calculate on blur
                    ->afterStateUpdated(function (callable $set, callable $get) {
                        $purchase = floatval($get('purchase_price'));
                        $cash     = floatval($get('cash_price'));
                        $profit   = $cash - $purchase;

                        $set('profit', $profit);
                        $set('profit_percentage', $purchase > 0 ? round(($profit / $purchase) * 100, 0) : 0);
                    })
                    ->formatStateUsing(fn ($state) => number_format($state, 0, '.', '')),

                TextInput::make('cash_price')
                    ->label(__('Cash Price'))
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->prefix(__('EGP'))
                    ->reactive()
                    ->lazy()  // ← only sync & calculate on blur
                    ->afterStateUpdated(function (callable $set, callable $get) {
                        $purchase = floatval($get('purchase_price'));
                        $cash     = floatval($get('cash_price'));
                        $profit   = $cash - $purchase;

                        $set('profit', $profit);
                        $set('profit_percentage', $purchase > 0 ? round(($profit / $purchase) * 100, 0) : 0);
                    })
                    ->formatStateUsing(fn ($state) => number_format($state, 0, '.', '')),

                TextInput::make('profit')
                    ->label(__('Profit (Auto)'))
                    ->disabled()
                    ->numeric()
                    ->prefix(__('EGP'))
                    ->default(0)
                    ->formatStateUsing(fn ($state) => number_format($state, 0, '.', '')),

                TextInput::make('profit_percentage')
                    ->label(__('Profit %'))
                    ->disabled()
                    ->numeric()
                    ->suffix('%')
                    ->default(0)
                    ->formatStateUsing(fn ($state) => number_format($state, 0, '.', '')),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->sortable()
                    ->searchable(),

                TextColumn::make('category.name')
                    ->label(__('Category'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('purchase_price')
                    ->label(__('Purchase Price'))
                    ->sortable()
                    ->getStateUsing(fn ($record) => number_format($record->purchase_price, 0, '.', '') . ' ' . __('EGP'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('cash_price')
                    ->label(__('Cash Price'))
                    ->sortable()
                    ->getStateUsing(fn ($record) => number_format($record->cash_price, 0, '.', '') . ' ' . __('EGP')),

                TextColumn::make('profit')
                    ->label(__('Profit'))
                    ->getStateUsing(fn ($record) => number_format($record->profit, 0, '.', '') . ' ' . __('EGP'))
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('profit_percentage')
                    ->label(__('Profit %'))
                    ->suffix('%')
                    ->getStateUsing(fn ($record) => number_format($record->profit_percentage, 0, '.', '')),

                TextColumn::make('stock')
                    ->label(__('Stock'))
                    ->sortable()
                    ->getStateUsing(fn ($record) => number_format($record->stock, 0, '.', ''))
                    ->color(fn ($record) => $record->stock > 0 ? 'success' : 'danger')
                    ->weight('bold'),

                IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean()
                    ->state(fn ($record) => $record->stock > 0)
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label(__('Category'))
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('provider')
                    ->label(__('Provider'))
                    ->relationship('provider', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('in_stock')
                    ->label(__('Stock Status'))
                    ->placeholder(__('All'))
                    ->trueLabel(__('In Stock'))
                    ->falseLabel(__('Out of Stock'))
                    ->queries(
                        true:  fn ($query) => $query->where('stock', '>', 0),
                        false: fn ($query) => $query->where('stock', '<=', 0),
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                Action::make('addStock')
                    ->label(__('Add Stock'))
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->form([
                        TextInput::make('quantity')
                            ->label(__('Quantity to Add'))
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->formatStateUsing(fn ($state) => number_format($state, 0, '.', '')),
                    ])
                    ->action(fn (Product $record, array $data) => $record->increment('stock', $data['quantity']))
                    ->visible(fn ($record) => $record->exists),

                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'   => Pages\ListProducts::route('/'),
            'create'  => Pages\CreateProduct::route('/create'),
            'edit'    => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
