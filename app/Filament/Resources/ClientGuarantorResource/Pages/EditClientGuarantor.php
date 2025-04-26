<?php

namespace App\Filament\Resources\ClientGuarantorResource\Pages;

use App\Filament\Resources\ClientGuarantorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClientGuarantor extends EditRecord
{
    protected static string $resource = ClientGuarantorResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
