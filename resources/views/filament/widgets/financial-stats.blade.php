<div>
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    @endpush

    {{-- Cards Section --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
        @foreach ($this->getCards() as $card)
            {!! $card->render() !!}
        @endforeach
    </div>
    
    {{-- Breakdown Section --}}
    <div class="breakdown-section mt-8">
        <h3 class="section-title text-lg font-semibold text-gray-700 dark:text-gray-300">
            {{ __('Payments Breakdown for') }} {{ $periodLabel }}
        </h3>
        <div class="table-wrapper mt-4">
            <table class="table w-full text-sm">
                <thead class="table-header bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="table-header-cell px-4 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">{{ __('ID #') }}</th>
                        <th class="table-header-cell px-4 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">{{ __('Client') }}</th>
                        <th class="table-header-cell px-4 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">{{ __('Date') }}</th>
                        <th class="table-header-cell px-4 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">{{ __('Type') }}</th>
                        <th class="table-header-cell px-4 py-2 text-right font-semibold text-gray-700 dark:text-gray-200">{{ __('Amount Paid') }}</th>
                        <th class="table-header-cell px-4 py-2 text-right font-semibold text-gray-700 dark:text-gray-200">{{ __('Capital') }}</th>
                        <th class="table-header-cell px-4 py-2 text-right font-semibold text-gray-700 dark:text-gray-200">{{ __('Profit') }}</th>
                    </tr>
                </thead>
                <tbody class="table-body">
                    @forelse ($explainRows as $row)
                        <tr class="table-row border-b dark:border-gray-700">
                            <td class="table-cell px-4 py-2 dark:text-gray-100" data-label="{{ __('ID #') }}">
                                {{ $row['sale_id'] }}
                            </td>
                            <td class="table-cell px-4 py-2 dark:text-gray-100" data-label="{{ __('Client') }}">
                                {{ $row['client'] }}
                            </td>
                            <td class="table-cell px-4 py-2 dark:text-gray-100" data-label="{{ __('Date') }}">
                                {{ $row['date'] }}
                            </td>
                            <td class="table-cell px-4 py-2 dark:text-gray-100" data-label="{{ __('Type') }}">
                                {{ $row['type'] }}
                            </td>
                            <td class="table-cell px-4 py-2 text-right dark:text-gray-100" data-label="{{ __('Amount Paid') }}">
                                {{ number_format($row['amount_paid']) }}
                            </td>
                            <td class="table-cell px-4 py-2 text-right dark:text-gray-100" data-label="{{ __('Capital') }}">
                                {{ number_format($row['capital']) }}
                            </td>
                            <td class="table-cell px-4 py-2 text-right dark:text-gray-100" data-label="{{ __('Profit') }}">
                                {{ number_format($row['profit']) }}
                            </td>
                        </tr>
                    @empty
                        <tr class="table-row">
                            <td class="table-cell empty-cell text-center py-4 text-gray-500 dark:text-gray-300" colspan="7">
                                {{ __('No data for this period.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="pagination-wrapper mt-4">
            {{ $explainRows->links() }}
        </div>
    </div>
</div>
