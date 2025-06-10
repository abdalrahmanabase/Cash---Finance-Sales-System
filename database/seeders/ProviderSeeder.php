<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Provider;
use App\Models\ProviderBill;
use App\Models\ProviderPayment;

class ProviderSeeder extends Seeder
{
    public function run(): void
    {
        // create 10 providers
        $providers = Provider::factory(5)->create();

        foreach ($providers as $provider) {
            // 1–3 bills each
            ProviderBill::factory(rand(1, 3))
                ->create([
                    'provider_id' => $provider->id,
                ]);

            // optionally, 2–5 payments per provider
            ProviderPayment::factory(rand(2, 5))
                ->create([
                    'provider_id' => $provider->id,
                ]);
        }
    }
}
