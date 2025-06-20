<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use App\Filament\Widgets\FinancialStats;

class FinancialOverview extends Page
{
    protected static string $view = 'filament.pages.financial-overview';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
     protected static ?string $navigationLabel = null;
    protected static ?string $navigationGroup = null;
    protected static ?string $title = null;

    public  function getTitle(): string
    {
        return __('Financial Overview');
    }

    public static function getNavigationLabel(): string
    {
        return __('Financial Overview'); // Translatable navigation label
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Financial Management'); // Translatable navigation group
    }
}
