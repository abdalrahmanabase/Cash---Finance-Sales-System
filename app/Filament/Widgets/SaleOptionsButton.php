<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class SaleOptionsWidget extends Widget
{
    protected static string $view = 'filament.widgets.sale-options-button';
    protected int | string | array $columnSpan = 'full'; // span full width
}