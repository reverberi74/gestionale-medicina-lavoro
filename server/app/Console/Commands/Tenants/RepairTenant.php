<?php

namespace App\Console\Commands\Tenants;

use App\Models\Tenant;
use App\Services\TenantProvisioningService;
use Illuminate\Console\Command;

class RepairTenant extends Command
{
    /**
     * Safe re-run for a tenant:
     * - (optional) create DB if missing
     * - configure tenant connection
     * - migrate tenant path
     * - seed base data (default yes)
     */
    protected $signature = 'tenants:repair
        {tenant : Tenant id or tenant key}
        {--timeout=10 : Lock timeout seconds}
        {--dry-run : Print actions without executing}
        {--no-create-db : Do not attempt CREATE DATABASE}
        {--no-seed : Skip tenant seeding}
        {--seed-class=Database\\Seeders\\Tenant\\TenantDatabaseSeeder : Seeder FQCN}';

    protected $description = 'Repair tenant database (safe re-run: create DB if missing, migrate tenant, seed base data).';

    public function handle(TenantProvisioningService $service): int
    {
        $identifier = (string) $this->argument('tenant');

        $tenant = Tenant::query()
            ->when(
                ctype_digit($identifier),
                fn ($q) => $q->where('id', (int) $identifier),
                fn ($q) => $q->where('key', $identifier)
            )
            ->first();

        if (! $tenant) {
            $this->error('TENANT_NOT_FOUND');
            return self::FAILURE;
        }

        $timeout = (int) $this->option('timeout');
        $dryRun = (bool) $this->option('dry-run');
        $createDb = ! (bool) $this->option('no-create-db');
        $seed = ! (bool) $this->option('no-seed');
        $seedClass = (string) $this->option('seed-class');

        try {
            $this->info("Repairing tenant: id={$tenant->id} key={$tenant->key} db={$tenant->db_name}");

            $result = $service->withTenantLock((int) $tenant->id, 'repair', $timeout, function () use ($service, $tenant, $dryRun, $createDb, $seed, $seedClass) {
                $dbName = (string) $tenant->db_name;

                if ($dryRun) {
                    return [
                        'ok' => true,
                        'dry_run' => true,
                        'tenant_id' => $tenant->id,
                        'tenant_key' => $tenant->key,
                        'db_name' => $dbName,
                        'actions' => [
                            $createDb ? 'create_database_if_not_exists' : 'skip_create_database',
                            'configure_tenant_connection',
                            'migrate_tenant_path_database/migrations/tenant',
                            $seed ? ('seed_'.$seedClass) : 'skip_seed',
                        ],
                    ];
                }

                if ($createDb) {
                    $service->createDatabaseIfNotExists($dbName);
                }

                $service->configureTenantConnection($dbName);
                $service->assertTenantConnection($dbName);

                $migrateExit = $service->runMigrations();

                $seedExit = null;
                if ($seed) {
                    $seedExit = $service->runSeed($seedClass);
                }

                return [
                    'ok' => true,
                    'dry_run' => false,
                    'tenant_id' => $tenant->id,
                    'tenant_key' => $tenant->key,
                    'db_name' => $dbName,
                    'create_db' => $createDb,
                    'migrate_exit' => $migrateExit,
                    'seed' => $seed,
                    'seed_class' => $seed ? $seedClass : null,
                    'seed_exit' => $seedExit,
                ];
            });

            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info('DONE');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('FAILED: '.$e->getMessage());
            return self::FAILURE;
        }
    }
}
