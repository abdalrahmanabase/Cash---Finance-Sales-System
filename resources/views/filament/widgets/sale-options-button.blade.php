<x-filament::widget>
    <x-filament::card class="bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900 dark:to-green-800 shadow-md rounded-lg p-6">
        <div class="flex gap-4">
            <x-filament::button
                tag="a"
                href="{{ \App\Filament\Resources\CashSaleResource::getUrl('create') }}"
                icon="heroicon-o-currency-dollar"
                size="lg"
                color="success"
                class="flex-1 justify-center py-3 text-lg"
            >
                {{ __('New Cash Sale') }}
            </x-filament::button>

            <x-filament::button
                tag="a"
                href="{{ \App\Filament\Resources\InstallmentSaleResource::getUrl('create') }}"
                icon="heroicon-o-credit-card"
                size="lg"
                color="primary"
                class="flex-1 justify-center py-3 text-lg"
            >
                {{ __('New Installment Sale') }}
            </x-filament::button>
        </div>
    </x-filament::card>
</x-filament::widget>
