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

    public static function getNavigationLabel(): string
    {
        return __('Client Guarantors');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Clients Management');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('client_id')
                ->label(__('Client'))
                ->relationship('client', 'name')
                ->required()
                ->searchable(),

            Forms\Components\TextInput::make('name')
                ->label(__('Guarantor Name'))
                ->required(),

            Forms\Components\TextInput::make('address')
                ->label(__('Address'))
                ->required(),

            Forms\Components\TextInput::make('phone')
                ->label(__('Phone'))
                ->required(),

            Forms\Components\TextInput::make('secondary_phone')
                ->label(__('Secondary Phone')),

            Forms\Components\TextInput::make('job')
                ->label(__('Job')),

            Forms\Components\TextInput::make('relation')
                ->label(__('Relation')),

            Forms\Components\TextInput::make('receipt_number')
                ->label(__('Receipt Number')),

            Forms\Components\FileUpload::make('proof_of_address')
                ->label(__('Proof of Address'))
                ->image()
                ->directory('guarantors/proof')
                ->maxSize(2048),

            Forms\Components\FileUpload::make('id_photo')
                ->label(__('ID Photo'))
                ->image()
                ->directory('guarantors/id')
                ->maxSize(2048),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')
                ->label(__('Guarantor Name'))
                ->searchable(),

            Tables\Columns\TextColumn::make('client.name')
                ->label(__('Client')),

            Tables\Columns\TextColumn::make('phone')
                ->label(__('Phone')),

            Tables\Columns\TextColumn::make('relation')
                ->label(__('Relation')),

            Tables\Columns\TextColumn::make('receipt_number')
                ->label(__('Receipt Number'))
                ->toggleable(),
        ])
        ->actions([
            Tables\Actions\CreateAction::make()->label(__('Add Client Guarantor')),

        ]);
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
