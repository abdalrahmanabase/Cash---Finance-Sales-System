<?php

namespace Database\Factories;

use App\Models\ClientGuarantor;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ClientGuarantorFactory extends Factory
{
    protected $model = ClientGuarantor::class;

    public function definition()
    {
        // Pick an existing client or create one
        $client = Client::inRandomOrder()->first() ?? Client::factory()->create();

        return [
            'client_id'        => $client->id,
            'name'             => $this->faker->name(),
            'address'          => $this->faker->address(),
            'phone'            => $this->faker->phoneNumber(),
            'secondary_phone'  => $this->faker->optional()->phoneNumber(),
            'proof_of_address' => null,
            'id_photo'         => null,
            'job'              => $this->faker->jobTitle(),
            'relation'         => $this->faker->randomElement(['parent','sibling','friend','spouse']),
            'receipt_number'   => Str::upper($this->faker->bothify('RGT-####')),
            'created_at'       => now(),
            'updated_at'       => now(),
        ];
    }
}
