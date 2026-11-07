<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Reference data needed in every environment, production included. */
    public function run(): void
    {
        $this->call([
            TagSeeder::class,
            AreaSeeder::class,
        ]);
    }
}
