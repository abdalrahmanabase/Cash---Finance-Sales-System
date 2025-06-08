<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CatigoryResource\Pages;
use App\Models\Category;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;

class CatigoryResource extends Resource
{
    protected static ?string $model = Category::class;
    protected static ?string $navigationIcon = 'heroicon-o-tag';

    public static function getNavigationLabel(): string
    {
        return __('Categories');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Products Management');
    }

    public static function getModelLabel(): string
    {
        return __('Category');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Categories');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->role !== 'partner';
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->role !== 'partner';
    }

    public static function canCreate(): bool
    {
        return in_array(auth()->user()->role, ['admin', 'super_admin']);
    }

    public static function canEdit($record): bool
    {
        return in_array(auth()->user()->role, ['admin', 'super_admin']);
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            TextInput::make('name')
                ->required()
                ->unique()
                ->label(__('Category Name')),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table->columns([
            TextColumn::make('name')
                ->label(__('Category Name'))
                ->searchable()
                ->sortable(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCatigories::route('/'),
            'create' => Pages\CreateCatigory::route('/create'),
            'edit' => Pages\EditCatigory::route('/{record}/edit'),
        ];
    }
}
