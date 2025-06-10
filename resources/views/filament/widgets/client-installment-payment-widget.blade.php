 <x-filament::widget>
        <x-filament::card class="bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900 dark:to-blue-800 shadow-md rounded-lg p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <x-heroicon-o-credit-card class="w-8 h-8 text-blue-600 dark:text-blue-400" />
                    <div>
                        <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                            {{ __('Client Installment Payments') }}
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            {{ __('Track and manage installment payments') }}
                        </p>
                    </div>
                </div>
                <a href="{{ route('filament.admin.pages.client-installment-payments') }}">
                    <x-filament::button
                        size="sm"
                        color="primary"
                        class="uppercase tracking-wide"
                    >
                        {{ __('Go to Payments') }}
                    </x-filament::button>
                </a>
            </div>
        </x-filament::card>
    </x-filament::widget>