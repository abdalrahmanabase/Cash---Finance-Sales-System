<?php

namespace App\Filament\Resources\CashSaleResource\Pages;

use App\Filament\Resources\CashSaleResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditCashSale extends EditRecord
{
    protected static string $resource = CashSaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('view_items')
                ->icon('heroicon-o-eye')
                ->url(fn () => static::getResource()::getUrl('view-items', ['record' => $this->record])),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}