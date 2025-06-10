<?php

namespace Database\Factories;

use App\Models\ProviderBill;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProviderBillFactory extends Factory
{
    protected $model = ProviderBill::class;

    public function definition()
    {
        return [
            // if you run this after Provider::factory()->create(), you can omit provider_id here
            'total_amount' => $this->faker->randomFloat(2, 100, 10000),
            'notes'        => $this->faker->optional()->sentence(),
            'image_path'   => null,
            'created_at'   => $this->faker->dateTimeBetween('-1 year','now')->format('Y-m-d'),
            'updated_at'   => now(),
        ];
    }
}
