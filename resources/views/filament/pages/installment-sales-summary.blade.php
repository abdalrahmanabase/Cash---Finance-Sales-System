<x-filament::page>
    {{-- <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach ($this->getHeaderWidgets() as $widget)
            <x-filament::widget>
                {{ $widget }}
            </x-filament::widget>
        @endforeach
    </div> --}}
    <div class="mt-8">
        {{ $this->table }}
    </div>
</x-filament::page>