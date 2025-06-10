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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel = 'Expenses';
    protected static ?string $navigationGroup = 'Financial Management';

    public static function getNavigationLabel(): string
    {
        return __('Expenses');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Financial Management');
    }

    protected static function getCurrencySymbol(): string
    {
        return app()->getLocale() === 'ar' ? 'جم' : 'EGP';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->label(__('Type'))
                    ->options([
                        'Paid For Owner' => __('Paid For Owner'),
                        'Rent'           => __('Rent'),
                        'Salary'         => __('Salary'),
                        'Bills'          => __('Bills'),
                        'Other'          => __('Other'),
                    ])
                    ->required()
                    ->reactive()
                    ->searchable(),

                Forms\Components\TextInput::make('type_manual')
                    ->label(__('Specify Other Type'))
                    ->maxLength(255)
                    ->hidden(fn (callable $get) => $get('type') !== 'Other')
                    ->dehydrated(fn ($state, $get) => $get('type') === 'Other')
                    ->required(fn (callable $get) => $get('type') === 'Other'),

                Forms\Components\TextInput::make('amount')
                    ->label(__('Amount (:currency)', ['currency' => static::getCurrencySymbol()]))

                    ->required()
                    ->numeric()
                    ->minValue(0),

                Forms\Components\DatePicker::make('date')
                    ->label(__('Expense Date'))
                    ->default(Carbon::today())
                    ->required(),

                Forms\Components\Textarea::make('description')
                    ->label(__('Description'))
                    ->rows(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label(__('Type'))
                    ->getStateUsing(fn ($record) => __($record->type))
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label(__('Amount (:currency)', ['currency' => static::getCurrencySymbol()]))

                    ->getStateUsing(fn ($record) => number_format($record->amount, 2, '.', ',') . ' ' . static::getCurrencySymbol())
                    ->sortable(),

                Tables\Columns\TextColumn::make('date')
                    ->label(__('Date'))
                    ->getStateUsing(fn ($record) => $record->date->format('Y-m-d'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('Type'))
                    ->options([
                        'Paid For Owner' => __('Paid For Owner'),
                        'Rent'           => __('Rent'),
                        'Salary'         => __('Salary'),
                        'Bills'          => __('Bills'),
                        'Other'          => __('Other'),
                    ]),

                Filter::make('period')
                    ->label(__('Date Range'))
                    ->form([
                        Forms\Components\Select::make('period')
                            ->label(__('Period'))
                            ->options([
                                '1'         => __('January'),
                                '2'         => __('February'),
                                '3'         => __('March'),
                                '4'         => __('April'),
                                '5'         => __('May'),
                                '6'         => __('June'),
                                '7'         => __('July'),
                                '8'         => __('August'),
                                '9'         => __('September'),
                                '10'        => __('October'),
                                '11'        => __('November'),
                                '12'        => __('December'),
                                'last_3'    => __('Last 3 Months'),
                                'last_6'    => __('Last 6 Months'),
                                'this_year' => __('This Year'),
                                'last_year' => __('Last Year'),
                            ])
                            ->placeholder(__('All Periods')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $period = $data['period'] ?? null;

                        if (!$period) {
                            return $query;
                        }

                        $now = Carbon::now();

                        if (ctype_digit($period)) {
                            return $query
                                ->whereYear('date', $now->year)
                                ->whereMonth('date', (int) $period);
                        }

                        if ($period === 'last_3') {
                            $start = $now->copy()->subMonths(3)->startOfMonth();
                            return $query->whereBetween('date', [$start, $now]);
                        }
                        if ($period === 'last_6') {
                            $start = $now->copy()->subMonths(6)->startOfMonth();
                            return $query->whereBetween('date', [$start, $now]);
                        }

                        if ($period === 'this_year') {
                            return $query->whereYear('date', $now->year);
                        }
                        if ($period === 'last_year') {
                            return $query->whereYear('date', $now->copy()->subYear()->year);
                        }

                        return $query;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label(__('Edit')),
                Tables\Actions\DeleteAction::make()->label(__('Delete')),
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
