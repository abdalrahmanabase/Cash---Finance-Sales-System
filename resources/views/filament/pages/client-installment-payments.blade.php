<x-filament::page>
    <div class="p-4 flex flex-col md:flex-row md:justify-between md:items-center gap-4">
        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            Client Installment Payments
        </h3>
        <form method="GET" class="flex items-center gap-2">
            <label for="client_filter" class="text-sm text-gray-700 dark:text-gray-300">Filter by Client:</label>
            <select id="client_filter" name="client" onchange="this.form.submit()" class="rounded border-gray-300 dark:bg-gray-800 dark:text-gray-100">
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
        <h4 class="font-semibold text-gray-800 dark:text-gray-200 mb-3">All Payments</h4>
        <div class="overflow-x-auto w-full rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm">
            <table class="min-w-full w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Client</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Paid Amount</th>
                        <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($this->getAllPaymentsFiltered() as $payment)
                        <tr class="group">
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100 group-hover:text-blue-600 dark:group-hover:text-yellow-300 transition">
                                {{ $payment['client'] }}
                            </td>
                            <td class="px-4 py-3 text-sm text-green-700 dark:text-green-400 font-semibold group-hover:text-blue-600 dark:group-hover:text-yellow-300 transition">
                                EGP {{ number_format($payment['amount'], 2) }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300 group-hover:text-blue-600 dark:group-hover:text-yellow-300 transition">
                                {{ \Carbon\Carbon::parse($payment['date'])->format('d-m-Y') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                No payments found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament::page>
