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
        $this->record->deductStock();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Get the RAW form data including unvalidated inputs
        $rawData = $this->form->getRawState();
        
        // Force include the down_payment from raw form data
        $data['down_payment'] = (float)($rawData['down_payment'] ?? 0);
        
        // Recalculate all dependent values
        $finalPrice = (float)($data['final_price'] ?? 0);
        $interestAmount = (float)($data['interest_amount'] ?? 0);
        $data['remaining_amount'] = max(0, ($finalPrice - $data['down_payment']) + $interestAmount);
        
        // Initialize empty payment history
        $data['payment_dates'] = [];
        $data['payment_amounts'] = [];
        
        return $data;
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction(),
            $this->getCancelFormAction()
        ];
    }
}