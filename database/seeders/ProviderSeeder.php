<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Provider;

class ProviderSeeder extends Seeder
{
    public function run(): void
    {
        Provider::factory()->count(10)->create();
    }
}
