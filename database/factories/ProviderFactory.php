<?php

namespace Database\Factories;

use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProviderFactory extends Factory
{
    protected $model = Provider::class;

    public function definition()
    {
        return [
            'name' => $this->faker->company(),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
