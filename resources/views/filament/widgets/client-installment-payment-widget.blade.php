<x-filament::widget>
    <x-filament::card>
        <div class="p-4 flex justify-between items-center">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                {{ __('Client Installment Payments') }}
            </h3>

           <a href="{{ route('filament.admin.pages.client-installment-payments') }}">
                 <x-filament::button color="primary">
                     {{ __('Go to Payment Page') }}
                 </x-filament::button>
            </a>
        </div>
    </x-filament::card>
</x-filament::widget>
