<?php

namespace App\Models;
use App\Models\Inventory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        return $this->belongsTo(Product::class)->with('inventory');
    }

    protected static function booted()
    {
        static::deleted(function (SaleItem $saleItem) {
            if ($saleItem->product && $saleItem->product->inventory) {
                $saleItem->product->inventory->updateStock(
                    quantity: $saleItem->quantity,
                    operation: 'add',
                    notes: "Restock from deleted sale item #{$saleItem->id}"
                );
            }
        });
    }
}
