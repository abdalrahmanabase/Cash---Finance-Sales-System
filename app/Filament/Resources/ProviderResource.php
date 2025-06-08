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

    public static function getNavigationGroup(): ?string
    {
        return 'Products Management';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Provider Information')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                ])->columns(2),
                
            Forms\Components\Section::make('Bills')
                ->schema([
                    Forms\Components\Repeater::make('bills')
                        ->relationship()
                        ->schema([
                            Forms\Components\FileUpload::make('image_path')
                                ->image()
                                ->directory('provider_bills')
                                ->preserveFilenames()
                                ->downloadable()
                                ->openable()
                                ->previewable(),
                                
                            Forms\Components\TextInput::make('total_amount')
                                ->numeric()
                                ->required(),
                                
                            Forms\Components\TextInput::make('amount_paid')
                                ->numeric()
                                ->required(),
                                
                            Forms\Components\Textarea::make('notes')
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
                ->searchable()
                ->sortable(),
                
            Tables\Columns\TextColumn::make('bills_count')
                ->counts('bills')
                ->label('Bills'),
                
            Tables\Columns\TextColumn::make('total_debt')
                ->label('Debt')
                ->money('EGP'),
        ])
        ->actions([
            
            // Add Payment Action
            Tables\Actions\Action::make('addPayment')
                ->label(__('Add Payment'))
                ->icon('heroicon-o-currency-dollar')
                ->color('success')
                ->form([
                    Forms\Components\TextInput::make('amount')
                        ->numeric()
                        ->required()
                        ->step(0)
                        ->label('Amount (EGP)'),
                        
                    Forms\Components\Textarea::make('notes')
                        ->label('Notes')
                        ->columnSpanFull(),
                ])
                ->action(function (Provider $record, array $data): void {
                    $record->payments()->create([
                        'amount' => $data['amount'],
                        'notes' => $data['notes'],
                    ]);
                    
                    Notification::make()
                        ->title('Payment added successfully')
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

