<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
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

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class)->with(['product.inventory']);
    }
    
  

    public function deductStock(): void
    {
        \DB::transaction(function () {
            $this->load(['items.product.inventory']);
            
            \Log::info("Starting stock deduction for sale #{$this->id}", [
                'item_count' => $this->items->count(),
                'sale_type' => $this->sale_type
            ]);
    
            foreach ($this->items as $item) {
                try {
                    \Log::debug("Processing item #{$item->id}", [
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity
                    ]);
    
                    if (!$item->product) {
                        \Log::error("Product not loaded for item", ['item_id' => $item->id]);
                        continue;
                    }
    
                    if (!$item->product->inventory) {
                        \Log::error("Inventory missing for product", [
                            'product_id' => $item->product_id,
                            'product_name' => $item->product->name
                        ]);
                        continue;
                    }
    
                    $inventory = $item->product->inventory;
                    $previousStock = $inventory->stock;
                    $newStock = max(0, $previousStock - $item->quantity);
    
                    // Direct database update to bypass any model events
                    $updated = \DB::table('inventories')
                        ->where('id', $inventory->id)
                        ->update(['stock' => $newStock]);
    
                    if ($updated !== 1) {
                        \Log::error("Inventory update failed", [
                            'inventory_id' => $inventory->id,
                            'rows_affected' => $updated
                        ]);
                        continue;
                    }
    
                    // Create history record - using direct DB insert
                    $historyId = \DB::table('inventory_histories')->insertGetId([
                        'inventory_id' => $inventory->id,
                        'operation' => 'subtract',
                        'quantity' => $item->quantity,
                        'previous_stock' => $previousStock,
                        'new_stock' => $newStock,
                        'notes' => "Sold in {$this->sale_type} sale #{$this->id}",
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
    
                    \Log::info("Inventory updated", [
                        'product_id' => $item->product_id,
                        'previous_stock' => $previousStock,
                        'new_stock' => $newStock,
                        'history_id' => $historyId
                    ]);
    
                } catch (\Exception $e) {
                    \Log::error("Inventory update failed", [
                        'error' => $e->getMessage(),
                        'item_id' => $item->id,
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
        });
    }
    public function verifyInventoryUpdate(): array
{
    $results = [];
    $this->load('items.product.inventory');
    
    foreach ($this->items as $item) {
        $status = [
            'product_id' => $item->product_id,
            'expected_stock' => ($item->product->inventory->stock + $item->quantity), // What stock should be if we reverse the sale
            'actual_stock' => $item->product->inventory->stock,
            'history_exists' => \DB::table('inventory_histories')
                ->where('inventory_id', $item->product->inventory->id)
                ->where('notes', 'like', "%sale #{$this->id}%")
                ->exists()
        ];
        
        $results[] = $status;
    }
    
    return $results;
}
    // public function deductStock() {
    //     foreach ($this->items as $item) {
    //         $product = $item->product;
    //         $inventory = $product->inventory;
    //         $quantitySold = $item->quantity;
        
    //         $inventory->updateStock(
    //             quantity: $quantitySold,
    //             operation: 'subtract',
    //             notes: "Sold in sale #{$this->id}"
    //         );
    //     }
    // }
  
    
    // protected static function booted()
    // {
    //     static::created(function ($sale) {
    //         $sale->syncInventory();
    //     });

    //     static::updated(function ($sale) {
    //         $sale = $sale->fresh();
    //         $sale->syncInventory();
    //     });
    // }

    public function syncInventory()
    {
        $this->load('items.product.inventory');
        
        foreach ($this->items as $item) {
            if ($item->product && $item->product->inventory) {
                $item->product->inventory->updateStock(
                    quantity: $item->quantity,
                    operation: 'subtract',
                    notes: "Sold in sale #{$this->id}"
                );
            }
        }
    }
    
    
}
