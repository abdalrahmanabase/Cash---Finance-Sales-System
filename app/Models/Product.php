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
        'name', 'code', 'purchase_price', 'cash_price', 'profit','stock',
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

    public function getProfitAttribute(): float
{
    return ($this->cash_price ?? 0) - ($this->purchase_price ?? 0);
}

public function getProfitPercentageAttribute(): float
{
    $purchase = $this->purchase_price ?? 0;
    $profit = $this->profit;
    return $purchase > 0 ? round(($profit / $purchase) * 100, 2) : 0;
}

}