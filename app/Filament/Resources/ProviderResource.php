<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProviderResource\Pages;
use App\Filament\Resources\ProviderResource\RelationManagers\PaymentsRelationManager;
use App\Models\Provider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;

class ProviderResource extends Resource
{
    protected static ?string $model = Provider::class;
    protected static ?string $navigationIcon = 'heroicon-o-cog';

    public static function getNavigationLabel(): string
    {
        return __('Providers');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Products Management');
    }

    public static function getModelLabel(): string
    {
        return __('Provider');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Providers');
    }

    protected static function getCurrencySymbol(): string
{
    return app()->getLocale() === 'ar' ? 'جم' : 'EGP';
}

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make(__('Provider Information'))
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label(__('Name'))
                        ->required()
                        ->maxLength(255),
                ])->columns(2),

            Forms\Components\Section::make(__('Bills'))
                ->schema([
                    Forms\Components\Repeater::make('bills')
                        ->relationship()
                        ->label(__('Bills'))
                        ->schema([
                            Forms\Components\FileUpload::make('image_path')
                                ->label(__('Bill Image'))
                                ->image()
                                ->directory('provider_bills')
                                ->preserveFilenames()
                                ->downloadable()
                                ->openable()
                                ->previewable(),

                            Forms\Components\TextInput::make('total_amount')
                                ->label(__('Total Amount'))
                                ->numeric()
                                ->required(),

                            Forms\Components\TextInput::make('amount_paid')
                                ->label(__('Amount Paid'))
                                ->numeric()
                                ->required(),

                            Forms\Components\Textarea::make('notes')
                                ->label(__('Notes'))
                                ->columnSpanFull(),
                        ])
                        ->columns(1)
                        ->collapsible()
                ])
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('bills_count')
                    ->counts('bills')
                    ->label(__('Bills')),

                Tables\Columns\TextColumn::make('total_debt')
                    ->label(__('Debt'))
                    ->formatStateUsing(fn ($state): string => number_format($state, 0) . ' ' . static::getCurrencySymbol()),
            ])
            ->actions([
                Tables\Actions\Action::make('addPayment')
                    ->label(__('Add Payment'))
                    ->icon('heroicon-o-currency-dollar')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label(__('Amount (:currency)', ['currency' => static::getCurrencySymbol()]))
                            ->numeric()
                            ->required()
                            ->step(0),

                        Forms\Components\Textarea::make('notes')
                            ->label(__('Notes'))
                            ->columnSpanFull(),
                    ])
                    ->action(function (Provider $record, array $data): void {
                        $record->payments()->create([
                            'amount' => $data['amount'],
                            'notes' => $data['notes'],
                        ]);

                        Notification::make()
                            ->title(__('Payment added successfully'))
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProviders::route('/'),
            'create' => Pages\CreateProvider::route('/create'),
            'edit' => Pages\EditProvider::route('/{record}/edit'),
        ];
    }
}