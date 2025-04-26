<?php
// app/Filament/Resources/ClientGuarantorResource.php
namespace App\Filament\Resources;

use App\Filament\Resources\ClientGuarantorResource\Pages;
use App\Models\ClientGuarantor;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;

class ClientGuarantorResource extends Resource
{
    protected static ?string $model = ClientGuarantor::class;
    protected static ?string $navigationIcon = 'heroicon-o-user';
    protected static ?string $navigationGroup = 'Clients Management';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('client_id')
                ->relationship('client', 'name')
                ->required()
                ->searchable(),

            Forms\Components\TextInput::make('name')->required(),
            Forms\Components\TextInput::make('address')->required(),
            Forms\Components\TextInput::make('phone')->required(),
            Forms\Components\TextInput::make('secondary_phone'),
            Forms\Components\TextInput::make('job'),
            Forms\Components\TextInput::make('relation'),
            Forms\Components\TextInput::make('receipt_number'),
            Forms\Components\FileUpload::make('proof_of_address')
                ->image()
                ->directory('guarantors/proof')
                ->maxSize(2048),
            Forms\Components\FileUpload::make('id_photo')
                ->image()
                ->directory('guarantors/id')
                ->maxSize(2048),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable(),
            Tables\Columns\TextColumn::make('client.name')->label('Client'),
            Tables\Columns\TextColumn::make('phone'),
            Tables\Columns\TextColumn::make('relation'),
            Tables\Columns\TextColumn::make('receipt_number')->toggleable(),
        ])
        ;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientGuarantors::route('/'),
            'create' => Pages\CreateClientGuarantor::route('/create'),
            'edit' => Pages\EditClientGuarantor::route('/{record}/edit'),
        ];
    }
}
