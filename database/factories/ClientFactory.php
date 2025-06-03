<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition()
    {
        return [
            'name' => $this->faker->unique()->name,
            'address' => $this->faker->address,
            'phone' => $this->faker->unique()->phoneNumber,
            'secondary_phone' => $this->faker->optional()->phoneNumber,
            'proof_of_address' => $this->faker->randomElement([
                'Electric Bill', 'Water Bill', 'Gas Bill', 'Phone Bill', 'Rental Agreement'
            ]),
            'id_photo' => $this->faker->imageUrl(200, 200, 'people'),
            'job' => $this->faker->jobTitle,
            'receipt_number' => $this->faker->unique()->numerify('RCPT-#####'),
        ];
    }
}
