<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        // Create some fixed categories, and some random ones
        $names = ['Electronics', 'Home Appliances', 'Furniture', 'Mobiles', 'Laptops', 'Clothing'];
        foreach ($names as $name) {
            Category::firstOrCreate(['name' => $name]);
        }
        Category::factory()->count(4)->create();
    }
}
