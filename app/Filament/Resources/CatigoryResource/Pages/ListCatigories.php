<?php

namespace App\Filament\Resources\CatigoryResource\Pages;

use App\Filament\Resources\CatigoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCatigories extends ListRecords
{
    protected static string $resource = CatigoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
