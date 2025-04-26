<?php

namespace App\Filament\Resources\CatigoryResource\Pages;

use App\Filament\Resources\CatigoryResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCatigory extends CreateRecord
{
    protected static string $resource = CatigoryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
