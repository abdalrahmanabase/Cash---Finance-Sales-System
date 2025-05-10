<?php

namespace App\Filament\Resources\InstallmentSaleResource\Pages;

use App\Filament\Resources\InstallmentSaleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInstallmentSale extends EditRecord
{
    protected static string $resource = InstallmentSaleResource::class;

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
        // Cache sale items before deletion for restocking
        $this->cachedItems = $this->record->items()->with('product')->get()->all();
    }

    protected function afterDelete(): void
    {
        // Restock products after deletion using cached items
        foreach ($this->cachedItems as $item) {
            if ($item->product) {
                $item->product->increment('stock', $item->quantity);
            }
        }
    }
}