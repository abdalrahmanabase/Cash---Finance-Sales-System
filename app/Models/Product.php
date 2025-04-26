<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Product extends Model
{
    use HasFactory;
    use SoftDeletes;
    protected $fillable = [
        'name', 'code', 'purchase_price', 'cash_price', 'profit',
        'is_active', 'category_id', 'provider_id'
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'cash_price' => 'decimal:2',
        'profit' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function provider()
    {
        return $this->belongsTo(Provider::class);
    }

    public function inventory()
    {
        return $this->hasOne(Inventory::class);
    }
    
    protected static function booted()
    {
        static::creating(function ($product) {
            $product->profit = $product->cash_price - $product->purchase_price;
        });

        static::created(function ($product) {
            // Hardcode initial stock to 0
            $inventory = $product->inventory()->create([
                'stock' => 0,
            ]);

            $inventory->histories()->create([
                'operation' => 'initial',
                'quantity' => 0,
                'previous_stock' => 0,
                'new_stock' => 0,
                'notes' => 'Auto-created with product',
            ]);
        });
    }

}