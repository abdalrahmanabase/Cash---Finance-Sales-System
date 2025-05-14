<?php
namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class ClientInstallmentPaymentWidget extends Widget
{
    protected static string $view = 'filament.widgets.client-installment-payment-widget';

    protected int | string | array $columnSpan = 'full';
}
