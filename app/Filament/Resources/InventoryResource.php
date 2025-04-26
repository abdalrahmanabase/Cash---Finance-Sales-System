<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryResource\Pages;
use App\Models\Inventory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InventoryResource extends Resource
{
    protected static ?string $model = Inventory::class;
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';
    protected static ?string $navigationGroup = 'Products Management';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('stock')
                ->numeric()
                ->required()
                ->minValue(0)
                ->label('Current Stock'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.name')
                    ->searchable()
                    ->sortable()
                    ->label('Product Name'),
                    
                TextColumn::make('stock')
                    ->sortable()
                    ->label('Current Stock')
                    ->color(function (Inventory $record) {
                        return $record->stock <= $record->product->minimum_stock 
                            ? 'danger' 
                            : 'success';
                    }),
                    
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->state(function (Inventory $record) {
                        return $record->stock > 0;
                    })
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime(),
            ])
            ->actions([
                Tables\Actions\Action::make('addStock')
                    ->icon('heroicon-o-plus')
                    ->form([
                        Forms\Components\TextInput::make('quantity')
                            ->numeric()
                            ->required()
                            ->minValue(1),
                    ])
                    ->action(function (Inventory $inventory, array $data) {
                        $previousStock = $inventory->stock;
                        $newStock = $previousStock + $data['quantity'];
                    
                        // Update inventory stock
                        $inventory->update(['stock' => $newStock]);
                    
                        // Record inventory history
                        $inventory->histories()->create([
                            'operation' => 'restock',
                            'quantity' => $data['quantity'],
                            'previous_stock' => $previousStock,
                            'new_stock' => $newStock,
                            'notes' => 'Manual stock addition',
                        ]);
                    }),                    
                    
                Tables\Actions\Action::make('viewHistory')
                    ->icon('heroicon-o-clock')
                    ->modalContent(function (Inventory $record) {
                        return view('filament.inventory-history', [
                            'history' => $record->histories()->latest()->get(),
                            'productName' => $record->product->name
                        ]);
                    })
                    ->modalHeading(fn (Inventory $record) => 'Stock History for ' . $record->product->name),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventories::route('/'),
            'create' => Pages\CreateInventory::route('/create'),
            'edit' => Pages\EditInventory::route('/{record}/edit'),
        ];
    }
}