<div>
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    @endpush
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
        @foreach ($this->getCards() as $card)
            {!! $card->render() !!}
        @endforeach
    </div>
    
        <div class="breakdown-section">
            <h3 class="section-title">
                {{ __('Payments Breakdown for') }} {{ $periodLabel }}
            </h3>
            <div class="table-wrapper">
                <table class="table">
                    <thead class="table-header">
                        <tr>
                              <th class="table-header-cell">{{ __('ID #') }}</th>
                              <th class="table-header-cell">{{ __('Client') }}</th>
                              <th class="table-header-cell">{{ __('Date') }}</th>
                              <th class="table-header-cell">{{ __('Type') }}</th>
                              <th class="table-header-cell">{{ __('Amount Paid') }}</th>
                              <th class="table-header-cell">{{ __('Capital') }}</th>
                              <th class="table-header-cell">{{ __('Profit') }}</th>
                        </tr>
                    </thead>
                    <tbody class="table-body">
                        @forelse ($explainRows as $row)
                            <tr class="table-row">
                                <td class="table-cell" data-label="Sale #">
                                    {{ $row['sale_id'] }}
                                </td>
                                <td class="table-cell" data-label="Client">
                                    {{ $row['client'] }}
                                </td>
                                <td class="table-cell" data-label="Date">
                                    {{ $row['date'] }}
                                </td>
                                <td class="table-cell" data-label="Type">
                                    {{ $row['type'] }}
                                </td>
                                <td class="table-cell text-right" data-label="Amount Paid">
                                    {{ number_format($row['amount_paid']) }}
                                </td>
                                <td class="table-cell text-right" data-label="Capital">
                                    {{ number_format($row['capital']) }}
                                </td>
                                <td class="table-cell text-right" data-label="Profit">
                                    {{ number_format($row['profit']) }}
                                </td>
                            </tr>
                        @empty
                            <tr class="table-row">
                                <td class="table-cell empty-cell" colspan="7">
                                      {{ __('No data for this period.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

             <div class="pagination-wrapper">
                {{ $explainRows->links() }}
 
            </div>
    </div>
</div>
