<x-filament::widget>
    <x-slot name="header">
        <h2 class="text-lg font-bold">Start a New Sale</h2>
    </x-slot>

    <div class="flex space-x-4">
        <x-filament::button
            color="success"
            tag="a"
            href="{{ route('filament.admin.resources.sales.cash.create') }}"
            icon="heroicon-o-currency-dollar"
        >
            New Cash Sale
        </x-filament::button>

        <x-filament::button
            color="primary"
            tag="a"
            href="{{ route('filament.admin.resources.sales.installment.create') }}"
            icon="heroicon-o-credit-card"
        >
            New Installment Sale
        </x-filament::button>
    </div>
</x-filament::widget>