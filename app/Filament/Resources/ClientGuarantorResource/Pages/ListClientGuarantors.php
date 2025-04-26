<?php

namespace App\Filament\Resources\ClientGuarantorResource\Pages;

use App\Filament\Resources\ClientGuarantorResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListClientGuarantors extends ListRecords
{
    protected static string $resource = ClientGuarantorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
