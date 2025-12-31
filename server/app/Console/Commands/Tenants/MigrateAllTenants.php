<?php

namespace App\Console\Commands\Tenants;

use Illuminate\Console\Command;

class MigrateAllTenants extends Command
{
    protected $signature = 'tenants:migrate-all
        {--timeout=10 : Lock timeout seconds}
        {--seed : Run tenant seeder after migrations}
        {--seed-class=Database\\Seeders\\Tenant\\TenantDatabaseSeeder : Seeder FQCN}';

    protected $description = 'Alias for tenants:migrate --all (and optional seeding).';

    public function handle(): int
    {
        $args = [
            '--all' => true,
            '--timeout' => (int) $this->option('timeout'),
        ];

        if ((bool) $this->option('seed')) {
            $args['--seed'] = true;
            $args['--seed-class'] = (string) $this->option('seed-class');
        }

        return (int) $this->call('tenants:migrate', $args);
    }
}
