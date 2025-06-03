<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
    //     $this->call([
    //         CategorySeeder::class,
    //     ProviderSeeder::class,
    //     ProductSeeder::class,
    //     ClientSeeder::class,
    //     FakeInstallmentSalesSeeder::class,
    // ]);


        User::updateOrCreate(
        ['email' => 'abasy@gmail.com'],
        [
            'name' => 'Abdalrahman Abase',
            'password' => bcrypt('11111111'),
            'role' => 'super_admin',
        ]
    );
    }
}
