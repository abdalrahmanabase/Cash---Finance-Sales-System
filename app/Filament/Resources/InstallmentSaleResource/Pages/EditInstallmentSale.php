<?php
namespace App\Filament\Resources\InstallmentSaleResource\Pages;

use App\Filament\Resources\InstallmentSaleResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditInstallmentSale extends EditRecord
{
    protected static string $resource = InstallmentSaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('complete')
                ->visible(fn () => $this->record->status === 'ongoing')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->action(function () {
                    $this->record->update(['status' => 'completed']);
                    $this->refreshFormData(['status']);
                }),
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