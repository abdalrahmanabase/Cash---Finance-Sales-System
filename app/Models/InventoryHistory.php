<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventoryHistory extends Model
{
    protected $fillable = [
        'inventory_id',
        'operation',
        'quantity',
        'previous_stock',
        'new_stock',
        'notes' // Removed: user_id, related_type, related_id
    ];

    protected $casts = [
        'previous_stock' => 'integer',
        'new_stock' => 'integer',
        'created_at' => 'datetime',
    ];

    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }


    public function getOperationLabelAttribute(): string
    {
        return match ($this->operation) {
            'add' => 'Restock',
            'subtract' => 'Sale',
            'set' => 'Adjustment',
            'sale_update' => 'Sale Update',
            default => ucfirst($this->operation)
        };
    }
}