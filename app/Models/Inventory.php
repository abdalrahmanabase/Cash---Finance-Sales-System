<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 
        'stock', 
        'minimum_stock', 
        'is_active',
        'last_restocked_at'
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
        'last_restocked_at' => 'datetime',
    ];

    // Stock status constants
    const STATUS_OUT_OF_STOCK = 'out-of-stock';
    const STATUS_LOW_STOCK = 'low-stock';
    const STATUS_IN_STOCK = 'in-stock';

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
    
    public function histories(): HasMany
    {
        return $this->hasMany(InventoryHistory::class)->latest();
    }
    public function updateStock(int $quantity, string $operation, ?string $notes = null): self
    {
        \Log::debug("Starting updateStock", [
            'product_id' => $this->product_id,
            'current_stock' => $this->stock,
            'quantity' => $quantity,
            'operation' => $operation
        ]);
    
        return \DB::transaction(function () use ($quantity, $operation, $notes) {
            $previousStock = $this->stock;
            $newStock = $this->calculateNewStock($previousStock, $quantity, $operation);
            
            \Log::debug("Calculated new stock", [
                'previous' => $previousStock,
                'new' => $newStock
            ]);
    
            if ($operation === 'subtract' && $previousStock < $quantity) {
                $error = "Insufficient stock for product {$this->product->name}";
                \Log::error($error);
                throw new \Exception($error);
            }
    
            $this->update(['stock' => $newStock]);
            \Log::debug("Stock updated in database");
    
            $this->recordHistory(
                operation: $operation,
                quantity: $quantity,
                previousStock: $previousStock,
                newStock: $newStock,
                notes: $notes
            );
    
            $this->syncProductStatus();
            
            return $this;
        });
    }

    // public function updateStock(int $quantity, string $operation, ?string $notes = null): self
    // {
    //     return \DB::transaction(function () use ($quantity, $operation, $notes) {
    //         $previousStock = $this->stock;
    //         $newStock = $this->calculateNewStock($previousStock, $quantity, $operation);
            
    //         if ($operation === 'subtract' && $previousStock < $quantity) {
    //             throw new \Exception("Insufficient stock for product {$this->product->name}");
    //         }

    //         $this->update(['stock' => $newStock]);
            
    //         $this->recordHistory(
    //             operation: $operation,
    //             quantity: $quantity,
    //             previousStock: $previousStock,
    //             newStock: $newStock,
    //             notes: $notes
    //         );
            
    //         $this->syncProductStatus();
            
    //         return $this;
    //     });
    // }

    protected function calculateNewStock(int $current, int $quantity, string $operation): int
    {
        return match ($operation) {
            'add' => $current + $quantity,
            'subtract' => max(0, $current - $quantity),
            'set' => max(0, $quantity),
            default => throw new \InvalidArgumentException("Invalid stock operation: {$operation}")
        };
    }

    protected function recordHistory(
        string $operation,
        int $quantity,
        int $previousStock,
        int $newStock,
        ?string $notes
    ): void {
        $this->histories()->create([
            'operation' => $operation,
            'quantity' => $quantity,
            'previous_stock' => $previousStock,
            'new_stock' => $newStock,
            'notes' => $notes
        ]);
    }

    protected function syncProductStatus(): void
    {
        if ($this->relationLoaded('product') && $this->product) {
            $shouldBeActive = $this->stock > 0;
            
            if ($this->product->is_active !== $shouldBeActive) {
                $this->product->update(['is_active' => $shouldBeActive]);
            }
        }
    }

    public function recordSale(SaleItem $saleItem, Sale $sale): self
    {
        return $this->updateStock(
            quantity: $saleItem->quantity,
            operation: 'subtract',
            notes: "Sold in {$sale->sale_type} sale #{$sale->id}"
        );
    }

    public function restock(int $quantity, ?string $source = null): self
    {
        return $this->updateStock(
            quantity: $quantity,
            operation: 'add',
            notes: $source ? "Restocked from {$source}" : 'Manual restock'
        );
    }

    public function getStockStatusAttribute(): string
    {
        if ($this->stock <= 0) {
            return self::STATUS_OUT_OF_STOCK;
        }
        if ($this->minimum_stock && $this->stock <= $this->minimum_stock) {
            return self::STATUS_LOW_STOCK;
        }
        return self::STATUS_IN_STOCK;
    }

    // Scopes
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where(function(Builder $q) {
            $q->whereColumn('stock', '<=', 'minimum_stock')
              ->orWhere('stock', '<=', 0);
        })->where('is_active', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query->where('stock', '<=', 0);
    }
}