<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Reference data needed in every environment, production included. */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            TagSeeder::class,
            AreaSeeder::class,
        ]);
    }
}
