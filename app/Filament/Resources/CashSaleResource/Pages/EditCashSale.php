<?php

namespace App\Filament\Resources\CashSaleResource\Pages;

use App\Filament\Resources\CashSaleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCashSale extends EditRecord
{
    protected static string $resource = CashSaleResource::class;

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


    protected array $cachedItems = [];

    protected function beforeDelete(): void
    {
        // Cache the items before deletion
        $this->cachedItems = $this->record->items()->with('product')->get()->all();
    }

    protected function afterDelete(): void
    {
        // Restock products using cached items
        foreach ($this->cachedItems as $item) {
            if ($item->product) {
                $item->product->increment('stock', $item->quantity);
            }
        }
    }
}
