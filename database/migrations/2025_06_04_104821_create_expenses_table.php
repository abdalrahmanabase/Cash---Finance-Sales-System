<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            // e.g. "Rent", "Salary", "Utilities", etc.
            $table->string('type');
            // amount of this expense
            $table->decimal('amount', 15, 2);
            // date the expense was recorded
            $table->date('date');
            // optional notes/details
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
