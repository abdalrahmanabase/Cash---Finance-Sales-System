<?php

namespace App\Filament\Resources\ClientGuarantorResource\Pages;

use App\Filament\Resources\ClientGuarantorResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateClientGuarantor extends CreateRecord
{
    protected static string $resource = ClientGuarantorResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
