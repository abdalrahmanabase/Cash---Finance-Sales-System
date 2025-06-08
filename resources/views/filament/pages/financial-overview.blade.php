@php
    $now = now();
    $year = $now->year;
    // Default to current month if not set
    $defaultPeriod = $year . '-' . str_pad($now->month, 2, '0', STR_PAD_LEFT);
    $period = request()->get('period', $defaultPeriod);

    $monthOptions = [
        'all_time'  => __('All Time'),
        'this_year' => $year . ' (' . __('This Year') . ')',
    ];
    foreach (range(1, 12) as $m) {
        $keyLabel = $year . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
        $monthOptions[$keyLabel] = $now->copy()->month($m)->format('F') . " $year";
    }
@endphp

<x-filament::page>
    {{-- 1) Period selector --}}
    <form method="GET" class="mb-6">
        <label
            for="period"
            class="text-sm font-semibold mr-2 text-gray-700 dark:text-gray-300"
        >
            {{ __('Period:') }}
        </label>
        <select
            name="period"
            id="period"
            onchange="this.form.submit()"
            class="inline-block rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900
                   text-base shadow-sm focus:ring-2 focus:ring-primary-600 focus:border-primary-600 transition
                   text-gray-900 dark:text-gray-100"
            style="min-width: 180px;"
        >
            @foreach ($monthOptions as $key => $label)
                <option value="{{ $key }}" {{ $period === $key ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
    </form>

    {{-- 2) Single Livewire widget for both summary cards & detailed table --}}
    @livewire(\App\Filament\Widgets\FinancialStats::class, ['period' => $period])
</x-filament::page>
