<div>
    <x-filament::modal
        heading="{{ __('Add Payment') }}"
        :show="$open"
        @close="$wire.open = false"
        width="md"
    >
        <x-filament::form wire:submit="submit">
            <x-filament::input.wrapper>
                <x-filament::input
                    type="number"
                    wire:model="amount"
                    label="{{ __('Amount (:currency)', ['currency' => $currencySymbol]) }}"
                    required
                    step="1"
                />
            </x-filament::input.wrapper>

            <x-filament::input.wrapper>
                <x-filament::input
                    type="text"
                    wire:model="notes"
                    label="{{ __('Notes') }}"
                />
            </x-filament::input.wrapper>

            <x-filament::button type="submit">
                {{ __('Submit Payment') }}
            </x-filament::button>
        </x-filament::form>
    </x-filament::modal>
</div>