<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
    
            Schema::create('products', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('code')->unique()->nullable();
                $table->decimal('purchase_price', 12, 2); 
                $table->decimal('cash_price', 12, 2); 
                $table->decimal('profit', 12, 2)->nullable();
                $table->decimal('stock', 12, 2)->default(0);
                $table->boolean('is_active')->default(true);
                $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
                $table->foreignId('provider_id')->constrained('providers')->onDelete('cascade');
                $table->timestamps();
                $table->softDeletes();
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
