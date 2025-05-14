<?php

namespace App\Filament\Resources\InstallmentSaleResource\Pages;

use App\Filament\Resources\InstallmentSaleResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Model;

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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure numeric values
        $data['down_payment'] = floatval($data['down_payment'] ?? 0);
        $data['final_price'] = floatval($data['final_price'] ?? 0);
        $data['interest_amount'] = floatval($data['interest_amount'] ?? 0);
        
        \Log::info('Form data before create:', $data);
        
        return $data;
    }
}
