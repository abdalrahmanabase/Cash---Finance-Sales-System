<?php

namespace Database\Factories;

use App\Models\ProviderPayment;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class ProviderPaymentFactory extends Factory
{
    protected $model = ProviderPayment::class;

    public function definition()
    {
        // Use the created_at timestamp as the payment date
        $dt   = $this->faker->dateTimeBetween('-1 year', 'now');
        $date = Carbon::instance($dt)->format('Y-m-d H:i:s');

        return [
            'provider_id' => Provider::inRandomOrder()->first()->id ?? Provider::factory(),
            'amount'      => $this->faker->randomFloat(2, 100, 10000),
            'notes'       => $this->faker->optional()->sentence(),
            'created_at'  => $date,
            'updated_at'  => $date,
        ];
    }
}
