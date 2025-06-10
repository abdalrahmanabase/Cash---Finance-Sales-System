{{-- filepath: resources/views/filament/pages/inventory.blade.php --}}
<x-filament::page>
    <div class="mb-8">
        <h2 class="text-2xl font-bold tracking-tight mb-2 dark:text-white">{{ __('Inventory Overview') }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 flex flex-col items-center">
                <span class="text-gray-500 dark:text-gray-300">{{ __('Total Products') }}</span>
                <span class="text-2xl font-bold text-primary-600 dark:text-primary-400">{{ $products->count() }}</span>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 flex flex-col items-center">
                <span class="text-gray-500 dark:text-gray-300">{{ __('Total Stock') }}</span>
                <span class="text-2xl font-bold text-primary-600 dark:text-primary-400">{{ $products->sum('stock') }}</span>
            </div>
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 flex flex-col items-center">
                <span class="text-gray-500 dark:text-gray-300">{{ __('Inventory Value') }}</span>
                <span class="text-2xl font-bold text-green-600 dark:text-green-400">جم {{ number_format($totalValue, 0) }}</span>
            </div>
        </div>
    </div>

    <div class="mb-4 flex flex-wrap gap-2">
        <x-filament::button id="showLowStockBtn" color="warning">
            {{ __('Show Low Stock Products') }}
        </x-filament::button>
        <x-filament::button id="showAllBtn" color="primary" class="hidden">
            {{ __('Show All Products') }}
        </x-filament::button>
    </div>

    {{-- Charts Side by Side --}}
    <div class="mb-8 grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- Bar Chart --}}
        <div class="flex flex-col items-center w-full">
            <h3 class="text-lg font-semibold mb-2 dark:text-white" id="chartTitleBar">{{ __('Top 10 Products by Stock') }}</h3>
            <div class="w-full flex justify-center">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 w-full" style="max-width: 480px;">
                    <canvas id="stockChartBar" style="width:100%;max-width:440px;height:300px;"></canvas>
                </div>
            </div>
        </div>
        {{-- Doughnut Chart --}}
        <div class="flex flex-col items-center w-full">
            <h3 class="text-lg font-semibold mb-2 dark:text-white" id="chartTitleDoughnut">{{ __('Stock Distribution by Category') }}</h3>
            <div class="w-full flex justify-center">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 w-full" style="max-width: 480px;">
                    <canvas id="stockChartDoughnut" style="width:100%;max-width:440px;height:300px;"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="filament-tables-table w-full text-sm" id="productsTable">
            <thead>
                <tr class="bg-gray-50 dark:bg-gray-700">
                    <th class="px-4 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">{{ __('Product') }}</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">{{ __('Category') }}</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">{{ __('Stock') }}</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">{{ __('Purchase Price') }}</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">{{ __('Total Value') }}</th>
                </tr>
            </thead>
            <tbody id="productsTableBody"></tbody>
        </table>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const topProducts = {!! $topProducts->toJson() !!};
        const stockByCategory = {!! $stockByCategory->toJson() !!};
        const allProducts = {!! $products->toJson() !!};
        const lowStockProducts = {!! $lowStockProducts->toJson() !!};

        function renderBarChart() {
            if (window.stockChartBarInstance) window.stockChartBarInstance.destroy();
            const ctx = document.getElementById('stockChartBar').getContext('2d');
            window.stockChartBarInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: topProducts.map(p => p.name),
                    datasets: [{
                        label: '{{ __('Stock') }}',
                        data: topProducts.map(p => p.stock),
                        backgroundColor: 'rgba(59, 130, 246, 0.7)',
                        borderColor: 'rgba(59, 130, 246, 1)',
                        borderWidth: 1,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { ticks: { color: document.documentElement.classList.contains('dark') ? '#fff' : '#000' } },
                        y: { beginAtZero: true, ticks: { color: document.documentElement.classList.contains('dark') ? '#fff' : '#000' } }
                    }
                }
            });
        }

        function renderDoughnutChart() {
            if (window.stockChartDoughnutInstance) window.stockChartDoughnutInstance.destroy();
            const ctx = document.getElementById('stockChartDoughnut').getContext('2d');
            window.stockChartDoughnutInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(stockByCategory),
                    datasets: [{
                        label: '{{ __('Stock by Category') }}',
                        data: Object.values(stockByCategory),
                        backgroundColor: [
                            '#3b82f6', '#10b981', '#f59e42', '#ef4444', '#a78bfa', '#f472b6',
                            '#facc15', '#6366f1', '#14b8a6', '#eab308'
                        ],
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: true },
                    },
                    layout: { padding: 10 }
                }
            });
        }

        function renderTable(products) {
            const tbody = document.getElementById('productsTableBody');
            tbody.innerHTML = '';
            if (products.length === 0) {
                tbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-gray-500 dark:text-gray-300">{{ __('No products found.') }}</td></tr>`;
                return;
            }
            products.forEach(product => {
                let category = product.category && product.category.name ? product.category.name : '{{ __('Uncategorized') }}';
                tbody.innerHTML += `
                    <tr class="border-b dark:border-gray-700">
                        <td class="px-4 py-2 dark:text-gray-100">${product.name}</td>
                        <td class="px-4 py-2 dark:text-gray-100">${category}</td>
                        <td class="px-4 py-2 dark:text-gray-100">${Number(product.stock).toLocaleString('en-US')}</td>
                        <td class="px-4 py-2 dark:text-gray-100">جم ${Number(product.purchase_price).toLocaleString('en-US')}</td>
                        <td class="px-4 py-2 dark:text-gray-100">جم ${(product.stock * product.purchase_price).toLocaleString('en-US')}</td>
                    </tr>
                `;
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            renderBarChart();
            renderDoughnutChart();
            renderTable(allProducts);

            document.getElementById('showLowStockBtn').addEventListener('click', function () {
                renderTable(lowStockProducts);
                this.classList.add('hidden');
                document.getElementById('showAllBtn').classList.remove('hidden');
            });

            document.getElementById('showAllBtn').addEventListener('click', function () {
                renderTable(allProducts);
                this.classList.add('hidden');
                document.getElementById('showLowStockBtn').classList.remove('hidden');
            });
        });
    </script>
    @endpush
</x-filament::page>