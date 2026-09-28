<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(BankHolidaySeeder::class);

        // Demo businesses and logins only outside production.
        if (! app()->isProduction()) {
            $this->call(DemoSeeder::class);
        }
    }
}
