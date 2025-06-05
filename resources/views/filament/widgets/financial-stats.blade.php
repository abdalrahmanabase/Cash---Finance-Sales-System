<div>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
        @foreach ($this->getCards() as $card)
            {!! $card->render() !!}
        @endforeach
    </div>
    <div class="mt-8">
        <h3 class="font-bold mb-2 text-lg">Payments Breakdown for {{ $periodLabel }}</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full border dark:border-gray-600 text-sm">
                <thead class="bg-gray-100 dark:bg-gray-700">
                <tr>
                    <th class="px-3 py-2 border dark:border-gray-600">Sale #</th>
                    <th class="px-3 py-2 border dark:border-gray-600">Client</th>
                    <th class="px-3 py-2 border dark:border-gray-600">Date</th>
                    <th class="px-3 py-2 border dark:border-gray-600">Type</th>
                    <th class="px-3 py-2 border dark:border-gray-600">Amount Paid</th>
                    <th class="px-3 py-2 border dark:border-gray-600">Capital</th>
                    <th class="px-3 py-2 border dark:border-gray-600">Profit</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($explainRows as $row)
                    <tr>
                        <td class="px-3 py-2 border dark:border-gray-600 text-center">{{ $row['sale_id'] }}</td>
                        <td class="px-3 py-2 border dark:border-gray-600">{{ $row['client'] }}</td>
                        <td class="px-3 py-2 border dark:border-gray-600">{{ $row['date'] }}</td>
                        <td class="px-3 py-2 border dark:border-gray-600">{{ $row['type'] }}</td>
                        <td class="px-3 py-2 border dark:border-gray-600 text-right">{{ number_format($row['amount_paid']) }}</td>
                        <td class="px-3 py-2 border dark:border-gray-600 text-right">{{ number_format($row['capital']) }}</td>
                        <td class="px-3 py-2 border dark:border-gray-600 text-right">{{ number_format($row['profit']) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td class="px-3 py-2 border dark:border-gray-600 text-center" colspan="7">No data for this period.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
