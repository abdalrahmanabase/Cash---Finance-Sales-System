<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\Category;
use App\Models\Provider;
use App\Models\ProviderPayment;
use App\Models\Product;
use App\Models\Client;
use App\Models\ClientGuarantor;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\MonthlyExpectation;
use Faker\Factory as Faker; // Import Faker

class FullFakeDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create(); // Create a Faker instance here

        // Disable FK checks and truncate tables
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        SaleItem::truncate();
        Sale::truncate();
        ClientGuarantor::truncate();
        Client::truncate();
        Product::truncate();
        ProviderPayment::truncate();
        Provider::truncate();
        Category::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // --- Create base data ---
        Category::factory(10)->create();
        $providers = Provider::factory(5)->create();
        foreach ($providers as $provider) {
            ProviderPayment::factory(rand(5, 15))->create(['provider_id' => $provider->id]);
        }
        Product::factory(50)->create();
        $clients = Client::factory(120)->create();
        foreach ($clients as $client) {
            ClientGuarantor::factory(rand(1, 2))->create(['client_id' => $client->id]);
        }
        
        $this->command->getOutput()->progressStart(12 * 80);
        
        // --- Create Sales using the new factory ---
        $startDate = Carbon::now()->subYear()->startOfMonth();

        for ($m = 0; $m < 12; $m++) {
            $month = $startDate->copy()->addMonths($m);

            // Create 50 cash sales for the month
            Sale::factory(50)
                ->cash()
                ->state(function (array $attributes) use ($clients, $month, $faker) { // Pass $faker into the closure
                    return [
                        'client_id' => $clients->random()->id,
                        'created_at' => $faker->dateTimeBetween($month->copy()->startOfMonth(), $month->copy()->endOfMonth()),
                    ];
                })
                ->create();
            $this->command->getOutput()->progressAdvance(50);

            // Create 30 installment sales for the month
            Sale::factory(30)
                ->installment()
                ->state(function (array $attributes) use ($clients, $month, $faker) { // Pass $faker into the closure
                    return [
                        'client_id' => $clients->random()->id,
                        'created_at' => $faker->dateTimeBetween($month->copy()->startOfMonth(), $month->copy()->endOfMonth()),
                    ];
                })
                ->create();
            $this->command->getOutput()->progressAdvance(30);
        }
        
        $this->command->getOutput()->progressFinish();
    }
}
