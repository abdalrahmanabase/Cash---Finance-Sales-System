<?php

namespace App\Filament\Resources\InstallmentSaleResource\Pages;

use App\Filament\Resources\InstallmentSaleResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateInstallmentSale extends CreateRecord
{
    protected static string $resource = InstallmentSaleResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        // Deduct stock after creating the sale
        $this->record->deductStock();
    }
}
