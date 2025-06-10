<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class SaleOptionsButton extends Widget
{
    protected static string $view = 'filament.widgets.sale-options-button';
    protected int | string | array $columnSpan = '1';
    protected static bool $isDiscovered = false;

    public static function getHeading(): string
    {
        return __('Sale Options');
    }
}