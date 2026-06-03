<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call([
            RankSeeder::class,
            SettingsSeeder::class,
            DemoNetworkSeeder::class,
        ]);
    }
}
