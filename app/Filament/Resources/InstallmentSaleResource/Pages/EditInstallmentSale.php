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
        // Cache old items before update to restore stock
        $this->cachedItems = $this->record->items()->with('product')->get()->all();
    }

    protected function afterSave(): void
    {
        // Restore stock for old items
        foreach ($this->cachedItems as $item) {
            if ($item->product) {
                $item->product->increment('stock', $item->quantity);
            }
        }
        // Deduct stock for new items
        $sale = $this->record->fresh('items.product');
        foreach ($sale->items as $item) {
            if ($item->product) {
                $item->product->decrement('stock', $item->quantity);
            }
        }
    }


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