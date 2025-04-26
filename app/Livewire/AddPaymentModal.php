<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Provider;
use Livewire\Attributes\On;  // Add this import

class AddPaymentModal extends Component
{
    public $providerId;
    public $amount;
    public $notes;
    public $open = false;

    // Use the new Livewire 3 event listener syntax
    #[On('openAddPaymentModal')] 
    public function openModal($providerId)
    {
        $this->providerId = $providerId;
        $this->open = true;
    }

    protected $rules = [
        'amount' => 'required|numeric|min:1',
        'notes' => 'nullable|string|max:255',
    ];

    public function submit()
    {
        $this->validate();

        $provider = Provider::find($this->providerId);
        $provider->payments()->create([
            'amount' => $this->amount,
            'notes' => $this->notes,
        ]);

        // Use dispatch instead of emit in Livewire 3
        $this->dispatch('paymentAdded');
        $this->reset();
        $this->open = false;
    }

    public function render()
    {
        return view('livewire.add-payment-modal');
    }
}