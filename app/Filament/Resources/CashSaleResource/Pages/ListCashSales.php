<?php

namespace App\Filament\Resources\CashSaleResource\Pages;

use App\Filament\Resources\CashSaleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCashSales extends ListRecords
{
    protected static string $resource = CashSaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
