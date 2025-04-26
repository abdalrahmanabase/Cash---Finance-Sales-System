<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancePool extends Model
{
    use HasFactory;

    protected $fillable = [
        'total_investment',
        'used_amount',
        'available_amount',
    ];

    // public function updateAvailableAmount()
    // {
    //     $this->available_amount = $this->total_investment - $this->used_amount;
    //     $this->save();
    // }
}
