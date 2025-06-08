<x-filament::page>

@push('styles')
        <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    @endpush
    <div class="p-4 flex flex-col md:flex-row md:justify-between md:items-center gap-4">
        <form method="GET" class="flex items-center gap-3">
            <label for="client_filter" class="text-sm font-medium text-gray-700 dark:text-white">{{ __('Filter by Client:') }}</label>
            <select
                id="client_filter"
                name="client"
                onchange="this.form.submit()"
                class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm
                       focus:border-primary-600 focus:ring focus:ring-primary-300 dark:border-gray-700 dark:bg-gray-800 dark:text-white dark:focus:ring-primary-700 transition"
            >
                <option value="">{{ __('All') }}</option>
                @foreach(\App\Models\Client::orderBy('name')->get() as $client)
                    <option value="{{ $client->id }}" @if(request('client') == $client->id) selected @endif>
                        {{ $client->name }}
                    </option>
                @endforeach
            </select>
        </form>
    </div>

     <div class="table-container">
        <h4 class="table-title">{{ __('All Payments') }}</h4>
        <div class="table-wrapper">
            <table class="table">
                <thead class="table-header">
                    <tr>
                        <th class="table-header-cell">{{ __('Client') }}</th>
                        <th class="table-header-cell">{{ __('Paid Amount') }}</th>
                        <th class="table-header-cell">{{ __('Date') }}</th>
                        <th class="table-header-cell">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="table-body">
                    @forelse ($allPayments as $payment)
                        <tr class="table-row">
                            <td class="table-cell" data-label="Client">
                                {{ $payment['client'] }}
                            </td>
                            <td class="table-cell" data-label="Paid Amount">
                                EGP {{ number_format($payment['amount'], 2) }}
                            </td>
                            <td class="table-cell" data-label="Date">
                                {{ \Carbon\Carbon::parse($payment['date'])->format('d-m-Y') }}
                            </td>
                            <td class="table-cell" data-label="Actions">
                                <div class="actions">
                                    <button 
                                        class="btn btn-edit"
                                        wire:click="editPaymentAction('{{ $payment['sale_id'] }}','{{ $payment['payment_index'] }}')"
                                        title="Edit"
                                    >✎</button>
                                    <button 
                                        class="btn btn-delete"
                                        wire:click="deletePaymentAction('{{ $payment['sale_id'] }}','{{ $payment['payment_index'] }}')"
                                        title="Delete"
                                    >🗑️</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr class="table-row">
                            <td class="table-cell empty-cell" colspan="4">
                                {{ __('No payments found.') }}
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
                        label="{{ __('Payment Amount') }}"
                        type="number"
                        step="0.01"
                        wire:model.defer="form.amount"
                        required
                        min="0.01"
                    />

                    <x-filament::input
                        label="{{ __('Payment Date') }}"
                        type="date"
                        wire:model.defer="form.date"
                        required
                    />

                    <div class="flex justify-end gap-2">
                        <x-filament::button type="submit" color="primary">
                            {{ __('Save') }}
                        </x-filament::button>
                        <x-filament::button
                            type="button"
                            color="secondary"
                            wire:click="$set('editingPayment', null)"
                            wire:click.prevent="$dispatch('close-modal', { id: 'edit-payment' })"
                        >
                            {{ __('Cancel') }}
                        </x-filament::button>
                    </div>
                </div>
            </form>
        @endif
    </x-filament::modal>

    <!-- Delete Confirmation Modal -->
    <x-filament::modal id="delete-payment" :heading="__('Delete Payment')">
        @if($deletingPayment)
            <p class="mb-4 text-gray-700 dark:text-white">{{ __('Are you sure you want to delete this payment?') }}</p>
            
            <div class="flex justify-end gap-2">
                <x-filament::button color="gray" wire:click="$set('deletingPayment', null)">
                    {{ __('Cancel') }}
                </x-filament::button>
                <x-filament::button color="danger" wire:click="confirmDeletePayment">
                    {{ __('Delete') }}
                </x-filament::button>
            </div>
        @endif
    </x-filament::modal>
</x-filament::page>