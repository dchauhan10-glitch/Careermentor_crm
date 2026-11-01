<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Reference data only. Dummy people and students: DemoDataSeeder. */
    public function run(): void
    {
        $this->call(ReferenceDataSeeder::class);
    }
}
