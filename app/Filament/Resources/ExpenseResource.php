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

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // 1) SELECT FOR PREDEFINED TYPES + “Other”
                Forms\Components\Select::make('type')
                    ->label('Type')
                    ->options([
                        'Paid For Owner' => 'Paid For Owner',
                        'Rent'           => 'Rent',
                        'Salary'         => 'Salary',
                        'Bills'          => 'Bills',
                        'Other'          => 'Other',
                    ])
                    ->required()
                    ->reactive()
                    ->searchable(),

                // 2) CONDITIONAL TEXT INPUT FOR “Other”
                Forms\Components\TextInput::make('type_manual')
                    ->label('Specify Other Type')
                    ->maxLength(255)
                    ->hidden(fn (callable $get) => $get('type') !== 'Other')
                    ->dehydrated(fn ($state, $get) => $get('type') === 'Other')
                    ->required(fn (callable $get) => $get('type') === 'Other'),

                // 3) AMOUNT AS BEFORE
                Forms\Components\TextInput::make('amount')
                    ->label('Amount (EGP)')
                    ->required()
                    ->numeric()
                    ->minValue(0),

                // 4) DATEPICKER, DEFAULT TO TODAY
                Forms\Components\DatePicker::make('date')
                    ->label('Expense Date')
                    ->default(Carbon::today())
                    ->required(),

                // 5) DESCRIPTION AS BEFORE
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
                // 1) Filter by Type
                SelectFilter::make('type')
                    ->label('Type')
                    ->options([
                        'Paid For Owner' => 'Paid For Owner',
                        'Rent'           => 'Rent',
                        'Salary'         => 'Salary',
                        'Bills'          => 'Bills',
                        'Other'          => 'Other',
                    ]),

                // 2) Period filter (months, last-3/6, this/last year)
                Filter::make('period')
                    ->label('Date Range')
                    ->form([
                        Forms\Components\Select::make('period')
                            ->label('Period')
                            ->options([
                                '1'         => 'January',
                                '2'         => 'February',
                                '3'         => 'March',
                                '4'         => 'April',
                                '5'         => 'May',
                                '6'         => 'June',
                                '7'         => 'July',
                                '8'         => 'August',
                                '9'         => 'September',
                                '10'        => 'October',
                                '11'        => 'November',
                                '12'        => 'December',
                                'last_3'    => 'Last 3 Months',
                                'last_6'    => 'Last 6 Months',
                                'this_year' => 'This Year',
                                'last_year' => 'Last Year',
                            ])
                            ->placeholder('All Periods'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $period = $data['period'] ?? null;

                        // 1) Nothing selected → unfiltered
                        if (! $period) {
                            return $query;
                        }

                        $now = Carbon::now();

                        // 2) Numeric month (1–12) → that month of current year
                        if (ctype_digit($period)) {
                            return $query
                                ->whereYear('date', $now->year)
                                ->whereMonth('date', (int) $period);
                        }

                        // 3) Last N months
                        if ($period === 'last_3') {
                            $start = $now->copy()->subMonths(3)->startOfMonth();
                            return $query->whereBetween('date', [$start, $now]);
                        }
                        if ($period === 'last_6') {
                            $start = $now->copy()->subMonths(6)->startOfMonth();
                            return $query->whereBetween('date', [$start, $now]);
                        }

                        // 4) Yearly presets
                        if ($period === 'this_year') {
                            return $query->whereYear('date', $now->year);
                        }
                        if ($period === 'last_year') {
                            return $query->whereYear('date', $now->copy()->subYear()->year);
                        }

                        // 5) Fallback → unfiltered
                        return $query;
                    }),
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
