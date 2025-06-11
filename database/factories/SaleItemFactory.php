<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SaleItem>
 */
class SaleItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Get a random product, or create one if none exist
        $product = Product::inRandomOrder()->first() ?? Product::factory()->create();
        
        return [
            'sale_id' => Sale::factory(),
            'product_id' => $product->id,
            'quantity' => $this->faker->numberBetween(1, 2),
            // Use the product's cash_price as the unit_price for the sale item
            'unit_price' => $product->cash_price,
        ];
    }
}
