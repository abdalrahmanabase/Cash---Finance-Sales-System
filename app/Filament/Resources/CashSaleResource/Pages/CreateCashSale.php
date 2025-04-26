<?php

namespace App\Filament\Resources\CashSaleResource\Pages;

use App\Filament\Resources\CashSaleResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateCashSale extends CreateRecord
{
    protected static string $resource = CashSaleResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}