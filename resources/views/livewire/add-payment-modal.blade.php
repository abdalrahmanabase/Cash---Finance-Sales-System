<div>
    <x-filament::modal
        heading="Add Payment"
        :show="$open"
        @close="$wire.open = false"
        width="md"
    >
        <x-filament::form wire:submit="submit">
            <x-filament::input.wrapper>
                <x-filament::input
                    type="number"
                    wire:model="amount"
                    label="Amount"
                    required
                    step="1"
                />
            </x-filament::input.wrapper>

            <x-filament::input.wrapper>
                <x-filament::input
                    type="text"
                    wire:model="notes"
                    label="Notes"
                />
            </x-filament::input.wrapper>

            <x-filament::button type="submit">
                Submit Payment
            </x-filament::button>
        </x-filament::form>
    </x-filament::modal>
</div>