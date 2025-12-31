<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class TenantProvisioningService
{
    /**
     * Provision a tenant database (idempotent).
     *
     * Options:
     * - timeout: int lock timeout seconds (default 10)
     * - dry_run: bool (default false)
     * - seed: bool (default true)
     */
    public function provision(Tenant $tenant, array $options = []): array
    {
        $timeout = (int) ($options['timeout'] ?? 10);
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $seed = array_key_exists('seed', $options) ? (bool) $options['seed'] : true;

        $dbName = (string) $tenant->db_name;
        $this->assertSafeDbName($dbName);

        return $this->withTenantLock((int) $tenant->id, 'provision', $timeout, function () use ($tenant, $dryRun, $seed, $dbName) {
            if ($dryRun) {
                return [
                    'ok' => true,
                    'dry_run' => true,
                    'tenant_id' => $tenant->id,
                    'tenant_key' => $tenant->key,
                    'db_name' => $dbName,
                    'actions' => [
                        'create_database_if_not_exists',
                        'configure_tenant_connection',
                        'migrate_tenant_path_database/migrations/tenant',
                        $seed ? 'seed_TenantDatabaseSeeder' : 'skip_seed',
                    ],
                ];
            }

            $this->createDatabaseIfNotExists($dbName);

            $this->configureTenantConnection($dbName);
            $this->assertTenantConnection($dbName);

            $migrateExit = $this->runMigrations();

            $seedExit = null;
            if ($seed) {
                $seedExit = $this->runSeed();
            }

            return [
                'ok' => true,
                'dry_run' => false,
                'tenant_id' => $tenant->id,
                'tenant_key' => $tenant->key,
                'db_name' => $dbName,
                'migrate_exit' => $migrateExit,
                'seed_exit' => $seedExit,
            ];
        });
    }

    /**
     * Creates the tenant database if missing.
     * NOTE: requires CREATE privilege for the DB user on the MySQL server.
     */
    public function createDatabaseIfNotExists(string $dbName): void
    {
        $this->assertSafeDbName($dbName);

        DB::connection('registry')->statement(
            "CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci"
        );
    }

    /**
     * Configure tenant connection at runtime and reconnect.
     */
    public function configureTenantConnection(string $dbName): void
    {
        $this->assertSafeDbName($dbName);

        config(['database.connections.tenant.database' => $dbName]);

        // Purge + reconnect to apply the new database name.
        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    /**
     * Sanity check: ensure we are connected to the expected tenant DB.
     */
    public function assertTenantConnection(string $expectedDbName): void
    {
        $currentDb = DB::connection('tenant')->selectOne('select database() as db');

        if (! $currentDb || (string) ($currentDb->db ?? '') !== $expectedDbName) {
            throw new RuntimeException(
                "TENANT_CONNECTION_MISMATCH: expected '{$expectedDbName}' got '".((string) ($currentDb->db ?? 'null'))."'"
            );
        }
    }

    /**
     * Run tenant migrations (database/migrations/tenant) on the configured tenant connection.
     * Returns exit code (0 on success) or throws on failure.
     */
    public function runMigrations(string $path = 'database/migrations/tenant'): int
    {
        $exit = Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => $path,
            '--force' => true,
        ]);

        if ($exit !== 0) {
            throw new RuntimeException(
                'TENANT_MIGRATE_FAILED: exit='.$exit.' output='.trim(Artisan::output())
            );
        }

        return $exit;
    }

    /**
     * Run tenant seeder on the configured tenant connection.
     * Returns exit code (0 on success) or throws on failure.
     */
    public function runSeed(string $class = 'Database\\Seeders\\Tenant\\TenantDatabaseSeeder'): int
    {
        $exit = Artisan::call('db:seed', [
            '--database' => 'tenant',
            '--class' => $class,
            '--force' => true,
        ]);

        if ($exit !== 0) {
            throw new RuntimeException(
                'TENANT_SEED_FAILED: exit='.$exit.' output='.trim(Artisan::output())
            );
        }

        return $exit;
    }

    /**
     * Generic tenant lock wrapper (MySQL advisory lock on registry connection).
     *
     * IMPORTANT:
     * - lock key is UNIQUE per tenant (not per "purpose")
     * - this prevents migrate/provision/repair concurrency for the same tenant
     *
     * @template T
     * @param  callable():T  $fn
     * @return T
     */
    public function withTenantLock(int $tenantId, string $purpose, int $timeout, callable $fn)
    {
        $lockKey = "gmdl:tenant:lock:{$tenantId}";

        $this->acquireLock($lockKey, $timeout, $purpose);

        try {
            return $fn();
        } finally {
            $this->releaseLock($lockKey);
        }
    }

    private function assertSafeDbName(string $dbName): void
    {
        // Safety: only allow safe db identifiers (no backticks, spaces, etc.)
        if (! preg_match('/\A[a-zA-Z0-9_]+\z/', $dbName)) {
            throw new RuntimeException("DB_NAME_NOT_SAFE: '{$dbName}'");
        }
    }

    private function acquireLock(string $key, int $timeout, string $purpose): void
    {
        $row = DB::connection('registry')->selectOne('SELECT GET_LOCK(?, ?) AS l', [$key, $timeout]);
        $ok = $row && ((int) ($row->l ?? 0) === 1);

        if (! $ok) {
            throw new RuntimeException("TENANT_LOCK_TIMEOUT: purpose={$purpose} key={$key}");
        }
    }

    private function releaseLock(string $key): void
    {
        try {
            DB::connection('registry')->selectOne('SELECT RELEASE_LOCK(?) AS r', [$key]);
        } catch (Throwable $e) {
            // best-effort
        }
    }
}
