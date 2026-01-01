<?php

namespace App\Console\Commands\Tenants;

use App\Models\Tenant;
use App\Services\TenantOperationRunService;
use App\Services\TenantProvisioningService;
use Illuminate\Console\Command;

class ProvisionTenant extends Command
{
    protected $signature = 'tenants:provision
        {tenant : Tenant id or tenant key}
        {--timeout=10 : Lock timeout seconds}
        {--dry-run : Print actions without executing}
        {--no-seed : Skip tenant seeding}';

    protected $description = 'Provision tenant database (create DB if missing, run tenant migrations, seed base data).';

    public function handle(TenantProvisioningService $service, TenantOperationRunService $runs): int
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

        $dryRun = (bool) $this->option('dry-run');

        $options = [
            'timeout' => (int) $this->option('timeout'),
            'dry_run' => $dryRun,
            'seed' => ! (bool) $this->option('no-seed'),
        ];

        $run = null;

        try {
            $this->info("Provisioning tenant: id={$tenant->id} key={$tenant->key} db={$tenant->db_name}");

            if (! $dryRun) {
                $run = $runs->start($tenant, 'provision', [
                    'tenant_key' => $tenant->key,
                    'db_name' => (string) $tenant->db_name,
                    'seed' => (bool) $options['seed'],
                    'timeout' => (int) $options['timeout'],
                    'trigger' => 'cli',
                ]);
            }

            $result = $service->provision($tenant, $options);

            if ($run) {
                $runs->markSuccess($run, [
                    'migrate_exit' => $result['migrate_exit'] ?? null,
                    'seed_exit' => $result['seed_exit'] ?? null,
                ]);
            }

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
