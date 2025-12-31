<?php

namespace App\Console\Commands\Tenants;

use App\Models\Tenant;
use App\Services\TenantProvisioningService;
use Illuminate\Console\Command;

class ProvisionTenant extends Command
{
    /**
     * Provision a tenant DB (create db + migrate tenant path + seed).
     *
     * Usage:
     * php artisan tenants:provision acme
     * php artisan tenants:provision 1
     */
    protected $signature = 'tenants:provision
        {tenant : Tenant id or tenant key}
        {--timeout=10 : Lock timeout seconds}
        {--dry-run : Print actions without executing}
        {--no-seed : Skip tenant seeding}';

    protected $description = 'Provision tenant database (create DB if missing, run tenant migrations, seed base data).';

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

        $options = [
            'timeout' => (int) $this->option('timeout'),
            'dry_run' => (bool) $this->option('dry-run'),
            'seed' => ! (bool) $this->option('no-seed'),
        ];

        try {
            $this->info("Provisioning tenant: id={$tenant->id} key={$tenant->key} db={$tenant->db_name}");

            $result = $service->provision($tenant, $options);

            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $this->info('DONE');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('FAILED: '.$e->getMessage());
            return self::FAILURE;
        }
    }
}
