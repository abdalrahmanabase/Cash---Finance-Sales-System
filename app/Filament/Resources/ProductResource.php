<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $navigationGroup = 'Products Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Product Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),

                        TextInput::make('code')
                            ->unique(ignoreRecord: true)
                            ->nullable()
                            ->maxLength(255),

                        Select::make('category_id')
                            ->relationship('category', 'name')
                            ->required()
                            ->preload()
                            ->searchable(),

                        Select::make('provider_id')
                            ->relationship('provider', 'name')
                            ->required()
                            ->preload()
                            ->searchable(),
                    ])
                    ->columns(3),

                    Section::make('Pricing')
                    ->schema([
                        TextInput::make('purchase_price')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->prefix('EGP')
                            ->reactive()
                            ->debounce(500)
                            ->afterStateUpdated(function (callable $set, callable $get) {
                                $purchase = floatval($get('purchase_price'));
                                $cash = floatval($get('cash_price'));
                                $profit = $cash - $purchase;

                                $set('profit', $profit);
                                $set('profit_percentage', $purchase > 0 ? round(($profit / $purchase) * 100, 2) : 0);
                            }),

                        TextInput::make('cash_price')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->prefix('EGP')
                            ->reactive()
                            ->debounce(500)
                            ->afterStateUpdated(function (callable $set, callable $get) {
                                $purchase = floatval($get('purchase_price'));
                                $cash = floatval($get('cash_price'));
                                $profit = $cash - $purchase;

                                $set('profit', $profit);
                                $set('profit_percentage', $purchase > 0 ? round(($profit / $purchase) * 100, 2) : 0);
                            }),

                
                        TextInput::make('profit')
                            ->disabled()
                            ->numeric()
                            ->prefix('EGP')
                            ->label('Profit (Auto)')
                            ->default(0),
                
                        TextInput::make('profit_percentage')
                            ->disabled()
                            ->numeric()
                            ->suffix('%')
                            ->label('Profit %')
                            ->default(0),
                    ])
                    ->columns(3),
                

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('category.name')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('purchase_price')
                    ->sortable()
                    ->money('EGP')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('cash_price')
                    ->sortable()
                    ->money('EGP'),

                TextColumn::make('profit')
                    ->sortable()
                    ->money('EGP')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('inventory.stock')
                    ->sortable()
                    ->label('Stock'),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->state(function (Product $record) {
                        return ($record->inventory->stock ?? 0) > 0;
                    })
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('provider')
                    ->relationship('provider', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}