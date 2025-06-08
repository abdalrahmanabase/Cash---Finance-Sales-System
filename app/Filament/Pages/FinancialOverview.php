<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Filament\Widgets\FinancialStats;


class FinancialOverview extends Page
{
    protected static string $view = 'filament.pages.financial-overview';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Financial Overview';
    protected static ?string $navigationGroup = 'Financial Management';


}
