<?php

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;

class TenantDatabaseSeeder extends Seeder
{
    /**
     * Seed base data for a tenant database.
     *
     * Keep it minimal for now: we will add calls when tenant tables exist.
     */
    public function run(): void
    {
        // Example later:
        // $this->call([
        //     RolesSeeder::class,
        //     SettingsSeeder::class,
        // ]);
    }
}
