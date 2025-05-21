<x-filament::page>
    <div class="p-4 flex flex-col md:flex-row md:justify-between md:items-center gap-4">
        <form method="GET" class="flex items-center gap-3">
            <label for="client_filter" class="text-sm font-medium text-gray-700 dark:text-white">Filter by Client:</label>
            <select
                id="client_filter"
                name="client"
                onchange="this.form.submit()"
                class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm
                       focus:border-primary-600 focus:ring focus:ring-primary-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:focus:ring-primary-700 transition"
            >
                <option value="">All</option>
                @foreach(\App\Models\Client::orderBy('name')->get() as $client)
                    <option value="{{ $client->id }}" @if(request('client') == $client->id) selected @endif>
                        {{ $client->name }}
                    </option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="px-4 pb-8">
        <h4 class="font-semibold text-gray-800 dark:text-white mb-4">All Payments</h4>
        <div class="overflow-x-auto w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm">
            <table class="min-w-full w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 dark:text-white">Client</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 dark:text-white">Paid Amount</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 dark:text-white">Date</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 dark:text-white">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($allPayments as $payment)
                        <tr>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-white transition">
                                <span
                                    class="cursor-pointer transition
                                    hover:text-primary-600 dark:hover:text-yellow-400
                                    hover:drop-shadow-[0_0_6px_rgba(79,70,229,0.7)] dark:hover:drop-shadow-[0_0_6px_rgba(202,138,4,0.7)]"
                                >
                                    {{ $payment['client'] }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-green-700 dark:text-green-400 font-semibold transition">
                                <span
                                    class="cursor-pointer transition
                                    hover:text-primary-600 dark:hover:text-yellow-400
                                    hover:drop-shadow-[0_0_6px_rgba(79,70,229,0.7)] dark:hover:drop-shadow-[0_0_6px_rgba(202,138,4,0.7)]"
                                >
                                    EGP {{ number_format($payment['amount'], 2) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-white transition">
                                <span
                                    class="cursor-pointer transition
                                    hover:text-primary-600 dark:hover:text-yellow-400
                                    hover:drop-shadow-[0_0_6px_rgba(79,70,229,0.7)] dark:hover:drop-shadow-[0_0_6px_rgba(202,138,4,0.7)]"
                                >
                                    {{ \Carbon\Carbon::parse($payment['date'])->format('d-m-Y') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <div class="flex gap-2">
                                    <button 
                                        wire:click="editPaymentAction('{{ $payment['sale_id'] }}', '{{ $payment['payment_index'] }}')"
                                        class="inline-flex items-center justify-center rounded border border-primary-600 bg-white px-2 py-1 text-primary-600 shadow-sm transition
                                               hover:bg-primary-600 hover:text-white dark:border-primary-400 dark:text-primary-400 dark:hover:bg-primary-400 dark:hover:text-gray-900 hover:drop-shadow-[0_0_8px_rgba(79,70,229,0.8)]"
                                        title="Edit"
                                    >
                                        <x-heroicon-o-pencil class="w-4 h-4" />
                                    </button>
                                    <button 
                                        wire:click="deletePaymentAction('{{ $payment['sale_id'] }}', '{{ $payment['payment_index'] }}')"
                                        class="inline-flex items-center justify-center rounded border border-red-600 bg-white px-2 py-1 text-red-600 shadow-sm transition
                                               hover:bg-red-600 hover:text-white dark:border-red-400 dark:text-red-400 dark:hover:bg-red-400 dark:hover:text-gray-900 hover:drop-shadow-[0_0_8px_rgba(220,38,38,0.8)]"
                                        title="Delete"
                                    >
                                        <x-heroicon-o-trash class="w-4 h-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                No payments found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            {{ $allPayments->links() }}
        </div>
    </div>

    <!-- Edit Payment Modal -->
    <x-filament::modal id="edit-payment" :heading="__('Edit Payment')">
        @if($editingPayment)
            @php
                $sale = \App\Models\Sale::find($editingPayment['saleId']);
                $payment = $sale->getAllPaymentsAttribute()[$editingPayment['paymentIndex']];
            @endphp
            
            <form wire:submit.prevent="updatePayment">
                <div class="space-y-4">
                    <x-filament::input
                        label="Payment Amount"
                        type="number"
                        step="0.01"
                        wire:model.defer="form.amount"
                        required
                        min="0.01"
                    />

                    <x-filament::input
                        label="Payment Date"
                        type="date"
                        wire:model.defer="form.date"
                        required
                    />

                    <div class="flex justify-end gap-2">
                        <x-filament::button type="submit" color="primary">
                            Save
                        </x-filament::button>
                        <x-filament::button
                            type="button"
                            color="secondary"
                            wire:click="$set('editingPayment', null)"
                            wire:click.prevent="$dispatch('close-modal', { id: 'edit-payment' })"
                        >
                            Cancel
                        </x-filament::button>
                    </div>
                </div>
            </form>
        @endif
    </x-filament::modal>

    <!-- Delete Confirmation Modal -->
    <x-filament::modal id="delete-payment" :heading="__('Delete Payment')">
        @if($deletingPayment)
            <p class="mb-4 text-gray-700 dark:text-white">Are you sure you want to delete this payment?</p>
            
            <div class="flex justify-end gap-2">
                <x-filament::button color="gray" wire:click="$set('deletingPayment', null)">
                    Cancel
                </x-filament::button>
                <x-filament::button color="danger" wire:click="confirmDeletePayment">
                    Delete
                </x-filament::button>
            </div>
        @endif
    </x-filament::modal>
</x-filament::page>
