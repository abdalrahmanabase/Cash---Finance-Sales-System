<x-filament::page>
    <x-filament::widgets
        :widgets="$this->getHeaderWidgets()"
        :columns="$this->getHeaderWidgetColumns()"
        class="fi-page-header-widgets mb-6"
    />

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Monthly Sales Chart -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Monthly Sales</h3>
            <canvas id="monthlySalesChart" height="300"></canvas>
        </div>

        <!-- Sales by Type Chart -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Sales by Type</h3>
            <canvas id="salesTypeChart" height="300"></canvas>
        </div>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Monthly Sales Chart
                new Chart(document.getElementById('monthlySalesChart'), {
                    type: 'line',
                    data: {
                        labels: @json($this->getMonthlyLabels()),
                        datasets: [{
                            label: 'Sales Amount',
                            data: @json($this->getMonthlyData()),
                            borderColor: 'rgb(79, 70, 229)',
                            tension: 0.1,
                            fill: true
                        }]
                    }
                });

                // Sales by Type Chart
                new Chart(document.getElementById('salesTypeChart'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Cash Sales', 'Installment Sales'],
                        datasets: [{
                            data: [
                                {{ Sale::where('sale_type', 'cash')->count() }},
                                {{ Sale::where('sale_type', 'installment')->count() }}
                            ],
                            backgroundColor: [
                                'rgba(54, 162, 235, 0.7)',
                                'rgba(255, 99, 132, 0.7)'
                            ]
                        }]
                    }
                });
            });
        </script>
    @endpush
</x-filament::page>