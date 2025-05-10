<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sale_id',
        'quantity',
        'unit_price',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    // protected static function booted()
    // {
    //     static::deleted(function (SaleItem $saleItem) {
    //         $inventory = $saleItem->product->inventory;
    
    //         if ($inventory) {
    //             $inventory->updateStock(
    //                 quantity: $saleItem->quantity,
    //                 operation: 'add',
    //                 notes: "Restock from deleted sale item #{$saleItem->id}"
    //             );
    //         }
    //     });
    // }

    // private function restockProcessed(): bool
    // {
    //     return Cache::has("saleitem_restocked_{$this->id}");
    // }
}