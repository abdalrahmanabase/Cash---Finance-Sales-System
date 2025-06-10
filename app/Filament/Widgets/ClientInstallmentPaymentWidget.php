<?php
namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class ClientInstallmentPaymentWidget extends Widget
{
    protected static string $view = 'filament.widgets.client-installment-payment-widget';

    protected int | string | array $columnSpan = '1';
    protected static bool $isDiscovered = false;

    public static function getHeading(): string
    {
        return __('Client Installment Payments');
    }
}
