<x-filament::page>
    <div class="w-full px-6 py-8 space-y-6">
        <p class="text-base text-gray-600 dark:text-gray-300">{{ __('Fill in the values to calculate the monthly installment payment.') }}</p>

        <form wire:submit.prevent>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-white dark:bg-gray-900 p-6 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">{{ __('Product Price') }}</label>
                    <input 
                        wire:model.lazy="productPrice" 
                        type="number" 
                        step="1"
                        class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">{{ __('Downpayment') }}</label>
                    <input 
                        wire:model.lazy="downpayment" 
                        type="number" 
                        step="1"
                        class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">{{ __('Interest Rate (%)') }}</label>
                    <input 
                        wire:model.lazy="interest" 
                        type="number" 
                        step="1"
                        class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">{{ __('Number of Months') }}</label>
                    <input 
                        wire:model.lazy="months" 
                        type="number" 
                        step="1"
                        class="w-full border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary-500"
                    />
                </div>
            </div>

            @if($productPrice && $downpayment && $interest !== null && $months)
                <div class="mt-8 p-6 bg-blue-50 dark:bg-blue-950 border border-blue-200 dark:border-blue-700 rounded-xl shadow-sm space-y-3">
                    <h3 class="text-xl font-semibold text-blue-900 dark:text-blue-200">{{ __('Calculation Result') }}</h3>

                    @php
                        $amountToFinance = $productPrice - $downpayment;
                        $interestAmount = $amountToFinance * ($interest / 100);
                        $finalPrice = $productPrice + $interestAmount;
                        $finalPricein = $amountToFinance + $interestAmount;
                        $monthlyPayment = $months > 0 ? $finalPricein / $months : 0;
                    @endphp

                    <div class="text-blue-800 dark:text-blue-100 space-y-1">
                        <p><strong>{{ __('Amount to Finance:') }}</strong> {{ number_format($amountToFinance, 2) }}</p>
                        <p><strong>{{ __('Interest Amount:') }}</strong> {{ number_format($interestAmount, 2) }}</p>
                        <p><strong>{{ __('Final Price (with Interest):') }}</strong> {{ number_format($finalPrice, 2) }}</p>
                        <p><strong>{{ __('Monthly Payment:') }}</strong> {{ number_format($monthlyPayment, 2) }}</p>
                    </div>
                </div>
            @endif
        </form>
    </div>
</x-filament::page>
