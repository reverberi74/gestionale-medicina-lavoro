<?php

namespace App\Console\Commands\Tenants;

use App\Models\Tenant;
use App\Services\TenantOperationRunService;
use App\Services\TenantProvisioningService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MigrateTenant extends Command
{
    protected $signature = 'tenants:migrate
        {tenant? : Tenant id or tenant key (required unless --all)}
        {--all : Migrate all active tenants}
        {--timeout=10 : Lock timeout seconds}
        {--seed : Run tenant seeder after migrations}
        {--seed-class=Database\\Seeders\\Tenant\\TenantDatabaseSeeder : Seeder FQCN}';

    protected $description = 'Run tenant migrations (and optional seeding) for one tenant or all active tenants.';

    public function handle(TenantProvisioningService $service, TenantOperationRunService $runs): int
    {
        $timeout = (int) $this->option('timeout');
        $seed = (bool) $this->option('seed');
        $seedClass = (string) $this->option('seed-class');

        $all = (bool) $this->option('all');
        $identifier = $this->argument('tenant');

        if (! $all && ($identifier === null || trim((string) $identifier) === '')) {
            $this->error('TENANT_REQUIRED (or use --all)');
            return self::FAILURE;
        }

        if ($all) {
            $tenants = Tenant::query()
                ->where('status', 'active')
                ->orderBy('id')
                ->get();

            if ($tenants->isEmpty()) {
                $this->info('No active tenants found.');
                return self::SUCCESS;
            }

            $batchId = (string) Str::uuid();

            $results = [];
            $failed = 0;

            foreach ($tenants as $tenant) {
                $run = null;

                try {
                    $this->info("Migrating tenant: id={$tenant->id} key={$tenant->key} db={$tenant->db_name}");

                    $run = $runs->start($tenant, 'migrate', [
                        'mode' => 'all',
                        'batch_id' => $batchId,
                        'tenant_key' => $tenant->key,
                        'db_name' => (string) $tenant->db_name,
                        'seed' => $seed,
                        'seed_class' => $seed ? $seedClass : null,
                        'timeout' => $timeout,
                        'trigger' => 'cli',
                    ]);

                    $result = $service->withTenantLock((int) $tenant->id, 'migrate', $timeout, function () use ($service, $tenant, $seed, $seedClass) {
                        $dbName = (string) $tenant->db_name;

                        $service->configureTenantConnection($dbName);
                        $service->assertTenantConnection($dbName);

                        $migrateExit = $service->runMigrations();

                        $seedExit = null;
                        if ($seed) {
                            $seedExit = $service->runSeed($seedClass);
                        }

                        return [
                            'ok' => true,
                            'tenant_id' => $tenant->id,
                            'tenant_key' => $tenant->key,
                            'db_name' => $dbName,
                            'migrate_exit' => $migrateExit,
                            'seed_exit' => $seedExit,
                        ];
                    });

                    $runs->markSuccess($run, [
                        'migrate_exit' => $result['migrate_exit'] ?? null,
                        'seed_exit' => $result['seed_exit'] ?? null,
                    ]);

                    $results[] = $result;
                } catch (\Throwable $e) {
                    $failed++;

                    if ($run) {
                        $runs->markFailed($run, $e);
                    }

                    $results[] = [
                        'ok' => false,
                        'tenant_id' => $tenant->id,
                        'tenant_key' => $tenant->key,
                        'db_name' => (string) $tenant->db_name,
                        'error' => $e->getMessage(),
                    ];

                    $this->error("FAILED tenant id={$tenant->id}: ".$e->getMessage());
                }
            }

            $summary = [
                'ok' => ($failed === 0),
                'mode' => 'all',
                'batch_id' => $batchId,
                'total' => count($results),
                'failed' => $failed,
                'seed' => $seed,
                'seed_class' => $seed ? $seedClass : null,
                'results' => $results,
            ];

            $this->line(json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return $failed === 0 ? self::SUCCESS : self::FAILURE;
        }

        // single tenant
        $identifier = (string) $identifier;

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

        $run = null;

        try {
            $this->info("Migrating tenant: id={$tenant->id} key={$tenant->key} db={$tenant->db_name}");

            $run = $runs->start($tenant, 'migrate', [
                'mode' => 'single',
                'tenant_key' => $tenant->key,
                'db_name' => (string) $tenant->db_name,
                'seed' => $seed,
                'seed_class' => $seed ? $seedClass : null,
                'timeout' => $timeout,
                'trigger' => 'cli',
            ]);

            $result = $service->withTenantLock((int) $tenant->id, 'migrate', $timeout, function () use ($service, $tenant, $seed, $seedClass) {
                $dbName = (string) $tenant->db_name;

                $service->configureTenantConnection($dbName);
                $service->assertTenantConnection($dbName);

                $migrateExit = $service->runMigrations();

                $seedExit = null;
                if ($seed) {
                    $seedExit = $service->runSeed($seedClass);
                }

                return [
                    'ok' => true,
                    'mode' => 'single',
                    'tenant_id' => $tenant->id,
                    'tenant_key' => $tenant->key,
                    'db_name' => $dbName,
                    'migrate_exit' => $migrateExit,
                    'seed_exit' => $seedExit,
                ];
            });

            $runs->markSuccess($run, [
                'migrate_exit' => $result['migrate_exit'] ?? null,
                'seed_exit' => $result['seed_exit'] ?? null,
            ]);

            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info('DONE');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            if ($run) {
                $runs->markFailed($run, $e);
            }

            $this->error('FAILED: '.$e->getMessage());
            return self::FAILURE;
        }
    }
}
