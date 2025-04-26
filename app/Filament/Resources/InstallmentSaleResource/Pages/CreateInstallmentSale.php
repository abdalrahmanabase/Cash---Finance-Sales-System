<?php
namespace App\Filament\Resources\InstallmentSaleResource\Pages;

use App\Filament\Resources\InstallmentSaleResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateInstallmentSale extends CreateRecord
{
    protected static string $resource = InstallmentSaleResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}