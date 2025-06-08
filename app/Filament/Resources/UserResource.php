<?php
namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function getNavigationGroup(): ?string
    {
        return __('User Management');
    }

    public static function canCreate(): bool
    {
        return auth()->user()->role === 'super_admin';
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            TextInput::make('name')
                ->required()
                ->label('Name'),

                TextInput::make('email')
                ->label('Email')
                ->required()
                ->email()
                ->unique(ignoreRecord: true), 
                
                
                TextInput::make('password')
                ->password()
                ->label('Password')
                ->required(fn (string $context) => $context === 'create')
                ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                ->dehydrated(fn ($state) => filled($state))
                ->maxLength(255),
            
            Select::make('role')
                ->options([
                    'super_admin' => 'Super Admin',
                    'admin' => 'Admin',
                    'partner' => 'Partner',
                ])
                ->default('admin')
                ->label('Role')
                ->disabled(fn ($record) => $record && $record->role !== null),
                
            TextInput::make('capital_share')
                ->numeric()
                ->label('Capital Share')
                ->default(0),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('email')->sortable()->searchable(),
                TextColumn::make('role')->sortable(),
                TextColumn::make('capital_share')
                ->sortable()
                ->formatStateUsing(fn ($state) => __('Currency') . ' ' . number_format($state, 0)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
