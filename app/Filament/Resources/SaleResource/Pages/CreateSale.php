<?php

namespace App\Filament\Resources\SaleResource\Pages;

use App\Filament\Resources\SaleResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSale extends CreateRecord
{
    protected static string $resource = SaleResource::class;
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    public function mount(): void
    {
        parent::mount();

        if (request()->has('sale_type')) {
            $this->form->fill([
                'sale_type' => request()->get('sale_type'),
            ]);
        }
    }
}
