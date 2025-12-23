<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RegistryDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // Create demo tenant "acme"
            $tenantId = DB::table('tenants')->updateOrInsert(
                ['key' => 'acme'],
                [
                    'name' => 'Acme Demo',
                    'db_name' => 'gmdl_tenant_demo',
                    'status' => 'active',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            // MySQL updateOrInsert doesn't return id, so we re-read it
            $tenant = DB::table('tenants')->where('key', 'acme')->first();

            // Primary domain for local dev (nip.io)
            DB::table('domains')->updateOrInsert(
                ['domain' => 'acme.127.0.0.1.nip.io'],
                [
                    'tenant_id' => $tenant->id,
                    'is_primary' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        });
    }
}
