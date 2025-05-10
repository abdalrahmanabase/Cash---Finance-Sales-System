<x-filament::widget class="!px-4 !py-6">
    <div class="flex flex-col gap-4">
        <x-filament::button
            color="success"
            tag="a"
            href="{{ \App\Filament\Resources\CashSaleResource::getUrl('create') }}"
            icon="heroicon-o-currency-dollar"
            class="w-full justify-center py-6 text-size-lg"
        >
            New Cash Sale
        </x-filament::button>
    
        <x-filament::button
            color="primary"
            tag="a"
            href="{{ \App\Filament\Resources\InstallmentSaleResource::getUrl('create') }}"
            icon="heroicon-o-credit-card"
            class="w-full justify-center py-6 text-size-lg"
        >
            New Installment Sale
        </x-filament::button>
    </div>
</x-filament::widget>