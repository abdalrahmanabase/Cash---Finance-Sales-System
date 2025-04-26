<div class="space-y-4">
    <h3 class="text-lg font-medium mb-4">History for {{ $productName }}</h3>
    
    @forelse($history as $entry)
        <div class="border rounded-lg p-4">
            <div class="flex justify-between">
                <span class="font-medium">{{ $entry['date'] }}</span>
                <span class="px-2 py-1 rounded-full text-xs 
                    @if($entry['operation'] === 'add') bg-green-100 text-green-800
                    @elseif($entry['operation'] === 'subtract') bg-red-100 text-red-800
                    @else bg-blue-100 text-blue-800 @endif">
                    {{ ucfirst($entry['operation']) }}
                </span>
            </div>
            <div class="grid grid-cols-3 gap-4 mt-2">
                <div>
                    <p class="text-sm text-gray-500">Quantity</p>
                    <p>{{ $entry['quantity'] }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Previous</p>
                    <p>{{ $entry['previous_stock'] }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-500">New</p>
                    <p>{{ $entry['new_stock'] }}</p>
                </div>
            </div>
        </div>
    @empty
        <p class="text-gray-500">No history available</p>
    @endforelse
</div>