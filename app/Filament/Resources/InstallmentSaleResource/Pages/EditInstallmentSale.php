<?php

namespace App\Filament\Resources\InstallmentSaleResource\Pages;

use App\Filament\Resources\InstallmentSaleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInstallmentSale extends EditRecord
{
    protected static string $resource = InstallmentSaleResource::class;

    protected array $cachedItems = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function beforeSave(): void
    {
        $this->cachedItems = $this->record->items()->with('product')->get()->all();
    }

    protected function afterSave(): void
    {
        foreach ($this->cachedItems as $item) {
            if ($item->product) {
                $item->product->increment('stock', $item->quantity);
            }
        }
        
        $sale = $this->record->fresh('items.product');
        foreach ($sale->items as $item) {
            if ($item->product) {
                $item->product->decrement('stock', $item->quantity);
            }
        }
    }

    protected function beforeDelete(): void
    {
        $this->cachedItems = $this->record->items()->with('product')->get()->all();
    }

    protected function afterDelete(): void
    {
        foreach ($this->cachedItems as $item) {
            if ($item->product) {
                $item->product->increment('stock', $item->quantity);
            }
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Get the raw form data including unvalidated inputs
        $rawData = $this->form->getRawState();
        
        // Force include the down_payment from raw form data
        $data['down_payment'] = (float)($rawData['down_payment'] ?? 0);
        
        // Recalculate remaining amount
        $finalPrice = (float)($data['final_price'] ?? 0);
        $interestAmount = (float)($data['interest_amount'] ?? 0);
        $data['remaining_amount'] = max(0, ($finalPrice - $data['down_payment']) + $interestAmount);
        
        // Initialize empty payment arrays if they don't exist
        $data['payment_dates'] = $data['payment_dates'] ?? [];
        $data['payment_amounts'] = $data['payment_amounts'] ?? [];
        
        // Remove the automatic down payment addition
        // (This is the key change from your original code)
        
        return $data;
    }
}