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

        // Safety: only allow safe db identifiers (no backticks, spaces, etc.)
        if (! preg_match('/\A[a-zA-Z0-9_]+\z/', $dbName)) {
            throw new RuntimeException("DB_NAME_NOT_SAFE: '{$dbName}'");
        }

        $lockKey = "gmdl:tenant:provision:{$tenant->id}";

        $this->acquireLock($lockKey, $timeout);

        try {
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

            // Sanity check: ensure we're connected to expected DB
            $currentDb = DB::connection('tenant')->selectOne('select database() as db');
            if (! $currentDb || (string) $currentDb->db !== $dbName) {
                throw new RuntimeException("TENANT_CONNECTION_MISMATCH: expected '{$dbName}' got '".($currentDb->db ?? 'null')."'");
            }

            $migrateExit = Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);

            if ($migrateExit !== 0) {
                throw new RuntimeException('TENANT_MIGRATE_FAILED: exit='.$migrateExit.' output='.trim(Artisan::output()));
            }

            $seedExit = null;
            if ($seed) {
                $seedExit = Artisan::call('db:seed', [
                    '--database' => 'tenant',
                    '--class' => 'Database\\Seeders\\Tenant\\TenantDatabaseSeeder',
                    '--force' => true,
                ]);

                if ($seedExit !== 0) {
                    throw new RuntimeException('TENANT_SEED_FAILED: exit='.$seedExit.' output='.trim(Artisan::output()));
                }
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
        } catch (Throwable $e) {
            throw $e;
        } finally {
            $this->releaseLock($lockKey);
        }
    }

    private function createDatabaseIfNotExists(string $dbName): void
    {
        // NOTE: requires CREATE privilege for the DB user on the MySQL server.
        // We keep charset/collation aligned with MySQL 8 defaults used in registry.
        DB::connection('registry')->statement(
            "CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci"
        );
    }

    private function configureTenantConnection(string $dbName): void
    {
        config(['database.connections.tenant.database' => $dbName]);

        // Purge + reconnect to apply the new database name.
        DB::purge('tenant');
        DB::reconnect('tenant');
    }

    private function acquireLock(string $key, int $timeout): void
    {
        $row = DB::connection('registry')->selectOne('SELECT GET_LOCK(?, ?) AS l', [$key, $timeout]);
        $ok = $row && ((int) ($row->l ?? 0) === 1);

        if (! $ok) {
            throw new RuntimeException("TENANT_PROVISION_LOCK_TIMEOUT: {$key}");
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
