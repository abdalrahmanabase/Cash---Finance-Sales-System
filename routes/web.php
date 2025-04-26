<?php

use Illuminate\Support\Facades\Route;
use App\Models\Product;
use App\Models\Sale;

Route::view('/', 'welcome');


// routes/web.php
Route::get('/test-sale/{id}', function($id) {
    $sale = \App\Models\Sale::findOrFail($id);
    
    // Manually trigger stock deduction
    $sale->deductStock();
    
    // Verify results
    return response()->json([
        'sale' => $sale->only(['id', 'sale_type']),
        'inventory_updates' => $sale->verifyInventoryUpdate(),
        'logs' => \DB::table('inventory_histories')
            ->where('notes', 'like', "%sale #{$id}%")
            ->get()
    ]);
});