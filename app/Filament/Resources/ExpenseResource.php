<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseResource\Pages;
use App\Models\Expense;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Expenses';
    protected static ?string $navigationGroup = 'Financial Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
                // 1) SELECT FOR PREDEFINED TYPES + “Other”
                //
                Forms\Components\Select::make('type')
                    ->label('Type')
                    ->options([
                        'Rent'   => 'Rent',
                        'Salary' => 'Salary',
                        'Bills'  => 'Bills',
                        'Other'  => 'Other',
                    ])
                    ->required()
                    ->reactive() // so that dependent fields update instantly

                    // If you want the user to be able to search through these options
                    ->searchable(),

                //
                // 2) CONDITIONAL TEXT INPUT FOR “Other”
                //
                Forms\Components\TextInput::make('type_manual')
                    ->label('Specify Other Type')
                    ->maxLength(255)
                    // Only show this field if the Select above is “Other”
                    ->hidden(fn (callable $get) => $get('type') !== 'Other')
                    // Only store this if “Other” was chosen—otherwise leave it null
                    ->dehydrated(fn ($state, $get) => $get('type') === 'Other')
                    ->required(fn (callable $get) => $get('type') === 'Other'),

                //
                // 3) AMOUNT AS BEFORE
                //
                Forms\Components\TextInput::make('amount')
                    ->label('Amount (EGP)')
                    ->required()
                    ->numeric()
                    ->minValue(0),

                //
                // 4) DATEPICKER, DEFAULT TO TODAY
                //
                Forms\Components\DatePicker::make('date')
                    ->label('Expense Date')
                    ->default(Carbon::today())
                    ->required(),

                //
                // 5) DESCRIPTION AS BEFORE
                //
                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->rows(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount (EGP)')
                    ->money('EGP', true)
                    ->sortable(),

                Tables\Columns\TextColumn::make('date')
                    ->label('Date')
                    ->date('Y-m-d')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('this_month')
                    ->label('This Month')
                    ->query(fn ($query) => $query
                        ->whereYear('date', now()->year)
                        ->whereMonth('date', now()->month)),

                Tables\Filters\Filter::make('this_year')
                    ->label('This Year')
                    ->query(fn ($query) => $query
                        ->whereYear('date', now()->year)),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'edit'   => Pages\EditExpense::route('/{record}/edit'),
        ];
    }
}
