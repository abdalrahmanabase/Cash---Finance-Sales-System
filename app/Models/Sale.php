<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'sale_type',
        'total_price',
        'discount',
        'interest_rate',
        'interest_amount',
        'final_price',
        'monthly_installment',
        'paid_amount',
        'remaining_amount',
        'months_count',
        'notes',
        'status',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
    
    public function deductStock(): void
{
    foreach ($this->items as $item) {
        $item->product->decrement('stock', $item->quantity);
    }
}

public function restockProducts(): void
{
    foreach ($this->items as $item) {
        $item->product->increment('stock', $item->quantity);
    }
}
}
