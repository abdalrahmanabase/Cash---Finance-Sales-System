<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProviderPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id', 
        'amount', 
        'notes',
        'payment_date'
    ];

    protected $casts = [
        'payment_date' => 'date',
    ];

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }
}