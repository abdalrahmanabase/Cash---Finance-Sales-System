<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Category;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition()
    {
        // Pick random existing category/provider
        $category = Category::inRandomOrder()->first();
        $provider = Provider::inRandomOrder()->first();

        $purchase = $this->faker->numberBetween(1000, 10000);
        $profit = $this->faker->numberBetween(400, 2000);
        $cash = $purchase + $profit;

        return [
            'name' => $this->faker->unique()->word(),
            'code' => $this->faker->unique()->ean8(),
            'purchase_price' => $purchase,
            'cash_price' => $cash,
            'profit' => $profit,
            'stock' => $this->faker->numberBetween(10, 100),
            'is_active' => true,
            'category_id' => $category ? $category->id : Category::factory(),
            'provider_id' => $provider ? $provider->id : Provider::factory(),
        ];
    }
}
