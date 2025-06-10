<?php

namespace App\Filament\Resources\ProviderResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Payments');
    }

    // This single method will provide the currency symbol for display.
    protected static function getCurrencySymbol(): string
    {
        return app()->getLocale() === 'ar' ? 'جم' : 'EGP';
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('amount')
                ->label(__('Amount'))
                ->numeric()
                ->required()
                ->prefix(static::getCurrencySymbol()),

            Forms\Components\Textarea::make('notes')
                ->label(__('Notes'))
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('amount')
            ->columns([
                // ### MAJOR FIX HERE ###
                // This manually formats the payment amount to ensure it always displays and uses English numerals.
                Tables\Columns\TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->formatStateUsing(fn ($state): string => number_format($state, 2) . ' ' . static::getCurrencySymbol())
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Payment Date'))
                    ->date('d-m-Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('notes')
                    ->label(__('Notes'))
                    ->limit(50)
                    ->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label(__('Create Payment')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}