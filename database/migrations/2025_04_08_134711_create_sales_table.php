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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->nullable()->constrained('clients')->onDelete('set null');
            $table->enum('sale_type', ['cash', 'installment'])->default('cash');
            
            // Pricing information
            $table->decimal('total_price', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('interest_rate', 5, 2)->nullable();
            $table->decimal('interest_amount', 12, 2)->nullable();
            $table->decimal('final_price', 12, 2);
            $table->decimal('down_payment', 12, 2)->default(0); 
            $table->decimal('monthly_installment', 12, 2)->nullable();
            $table->decimal('remaining_amount', 12, 2)->nullable();
            $table->integer('months_count')->nullable();

            // Installment tracking
            $table->json('payment_dates')->nullable();   
            $table->json('payment_amounts')->nullable(); 
            
            $table->enum('status', ['ongoing', 'completed'])->default('ongoing');
            $table->text('notes')->nullable();
            $table->unsignedTinyInteger('preferred_payment_day')->nullable();
            $table->date('next_payment_date')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
