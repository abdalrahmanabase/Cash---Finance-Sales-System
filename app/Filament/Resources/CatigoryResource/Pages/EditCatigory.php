<?php

namespace App\Filament\Resources\CatigoryResource\Pages;

use App\Filament\Resources\CatigoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCatigory extends EditRecord
{
    protected static string $resource = CatigoryResource::class;

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
