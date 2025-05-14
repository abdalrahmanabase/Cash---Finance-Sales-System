<x-filament::page>
    <div class="p-4 flex justify-between items-center">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            Client Installment Payments
        </h3>

        @foreach ($this->getActions() as $action)
            {{ $action }}
        @endforeach
    </div>
</x-filament::page>
