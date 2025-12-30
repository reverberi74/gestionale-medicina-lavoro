<?php

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TenantDatabaseSeeder extends Seeder
{
    /**
     * Seed base data for a tenant database.
     *
     * Keep it minimal and idempotent.
     */
    public function run(): void
    {
        $now = now();

        // Idempotente: updateOrInsert su key
        DB::connection('tenant')->table('tenant_settings')->updateOrInsert(
            ['key' => 'schema_version'],
            ['value' => json_encode(1), 'updated_at' => $now, 'created_at' => $now]
        );

        DB::connection('tenant')->table('tenant_settings')->updateOrInsert(
            ['key' => 'timezone'],
            ['value' => json_encode('Europe/Rome'), 'updated_at' => $now, 'created_at' => $now]
        );

        DB::connection('tenant')->table('tenant_settings')->updateOrInsert(
            ['key' => 'locale'],
            ['value' => json_encode('it'), 'updated_at' => $now, 'created_at' => $now]
        );
    }
}
