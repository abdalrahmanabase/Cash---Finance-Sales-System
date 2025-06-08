<div class="space-y-4">
    <div class="border-b pb-2">
        <h3 class="text-lg font-medium">{{ __('Items in Sale') }}</h3>
    </div>
    
    <div class="space-y-2">
        @foreach($items as $item)
        <div class="border p-3 rounded-lg">
            <div class="flex justify-between">
                <span class="font-medium">{{ $item->product->name }}</span>
                <span>{{ $item->quantity }} × {{ number_format($item->unit_price, 2) }} جم</span>
            </div>
        </div>
        @endforeach
    </div>
</div>