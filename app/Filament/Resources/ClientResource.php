<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClientResource\Pages;
use App\Models\Client;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;
    protected static ?string $navigationIcon = 'heroicon-o-user';
    protected static ?string $navigationGroup = 'Clients Management';

    public static function getNavigationGroup(): ?string
    {
        return __('Clients Management');
    }

    public static function getModelLabel(): string
    {
        return __('Client');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Clients');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Client Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label('Client Name'),

                        TextInput::make('address')
                            ->required()
                            ->maxLength(255)
                            ->label('Client Address'),

                        TextInput::make('phone')
                            ->required()
                            ->maxLength(11)
                            ->label('Client Phone'),

                        TextInput::make('secondary_phone')
                            ->nullable()
                            ->maxLength(11)
                            ->label('Secondary Phone'),

                        FileUpload::make('proof_of_address')
                            ->directory('client-documents')
                            ->nullable()
                            ->image()
                            ->preserveFilenames()
                                ->downloadable()
                                ->openable()
                                ->previewable()
                            ->label('Proof of Address'),

                        FileUpload::make('id_photo')
                            ->directory('client-documents')
                            ->nullable()
                            ->image()
                            ->preserveFilenames()
                                ->downloadable()
                                ->openable()
                                ->previewable()
                            ->label('ID Photo'),

                        TextInput::make('receipt_number')
                            ->nullable()
                            ->maxLength(255)
                            ->label('Receipt Number'),

                        TextInput::make('job')
                            ->nullable()
                            ->maxLength(255)
                            ->label('Job'),
                    ])
                    ->columns(2),

                Section::make('Guarantors')
                    ->schema([
                        Repeater::make('guarantors')
                            ->relationship()
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255)
                                    ->label('Guarantor Name'),

                                TextInput::make('address')
                                    ->required()
                                    ->maxLength(255)
                                    ->label('Guarantor Address'),

                                TextInput::make('phone')
                                    ->required()
                                    ->maxLength(11)
                                    ->label('Guarantor Phone'),

                                TextInput::make('secondary_phone')
                                    ->nullable()
                                    ->maxLength(11)
                                    ->label('Secondary Phone'),

                                FileUpload::make('proof_of_address')
                                    ->directory('guarantor-documents')
                                    ->nullable()
                                    ->image()
                                    ->preserveFilenames()
                                    ->downloadable()
                                    ->openable()
                                    ->previewable()
                                    ->label('Guarantor Proof of Address'),

                                FileUpload::make('id_photo')
                                    ->directory('guarantor-documents')
                                    ->nullable()
                                    ->image()
                                    ->preserveFilenames()
                                    ->downloadable()
                                    ->openable()
                                    ->previewable()
                                    ->label('Guarantor ID Photo'),

                                TextInput::make('relation')
                                    ->nullable()
                                    ->maxLength(255)
                                    ->label('Relation to Client'),

                                TextInput::make('receipt_number')
                                    ->nullable()
                                    ->maxLength(255)
                                    ->label('Guarantor Receipt Number'),
                            ])
                            ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                            ->minItems(0)
                            ->maxItems(2)
                            ->columns(2),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('phone')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('guarantors_count')
                    ->counts('guarantors')
                    ->label('Guarantors')
                    ->sortable(),
            ])
            ->filters([
                // Add filters if needed
            ])
            ;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClients::route('/'),
            'create' => Pages\CreateClient::route('/create'),
            'edit' => Pages\EditClient::route('/{record}/edit'),
        ];
    }
}