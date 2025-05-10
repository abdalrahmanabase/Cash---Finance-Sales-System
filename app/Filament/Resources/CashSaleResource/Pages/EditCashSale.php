<?php

namespace App\Filament\Resources\CashSaleResource\Pages;

use App\Filament\Resources\CashSaleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\Product;
use App\Models\Sale;

class EditCashSale extends EditRecord
{
    protected static string $resource = CashSaleResource::class;

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
