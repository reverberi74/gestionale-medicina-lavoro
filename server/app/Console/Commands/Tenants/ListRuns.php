<?php

namespace App\Console\Commands\Tenants;

use App\Models\Tenant;
use App\Models\TenantOperationRun;
use Illuminate\Console\Command;

class ListRuns extends Command
{
    protected $signature = 'tenants:runs
        {--tenant= : Tenant id or tenant key}
        {--action= : provision|migrate|repair}
        {--status= : started|success|failed}
        {--limit=20 : Max rows}
        {--json : Output JSON}';

    protected $description = 'List tenant operation runs (provision/migrate/repair) from registry.';

    public function handle(): int
    {
        $limit = max(1, min(200, (int) $this->option('limit')));
        $action = $this->option('action');
        $status = $this->option('status');
        $tenantOpt = $this->option('tenant');

        $tenantId = null;
        if (is_string($tenantOpt) && trim($tenantOpt) !== '') {
            $tenantOpt = trim($tenantOpt);

            $tenant = Tenant::query()
                ->when(
                    ctype_digit($tenantOpt),
                    fn ($q) => $q->where('id', (int) $tenantOpt),
                    fn ($q) => $q->where('key', $tenantOpt)
                )
                ->first();

            if (! $tenant) {
                $this->error('TENANT_NOT_FOUND');
                return self::FAILURE;
            }

            $tenantId = (int) $tenant->id;
        }

        $q = TenantOperationRun::query()
            ->when($tenantId !== null, fn ($qq) => $qq->where('tenant_id', $tenantId))
            ->when(is_string($action) && trim($action) !== '', fn ($qq) => $qq->where('action', trim($action)))
            ->when(is_string($status) && trim($status) !== '', fn ($qq) => $qq->where('status', trim($status)))
            ->orderByDesc('id')
            ->limit($limit);

        $runs = $q->get();

        // map tenant_id -> tenant_key (utile per table)
        $tenantIds = $runs->pluck('tenant_id')->filter()->unique()->values()->all();
        $tenantKeys = [];
        if (! empty($tenantIds)) {
            $tenantKeys = Tenant::query()
                ->whereIn('id', $tenantIds)
                ->get(['id', 'key'])
                ->pluck('key', 'id')
                ->all();
        }

        if ((bool) $this->option('json')) {
            $payload = $runs->map(function ($r) use ($tenantKeys) {
                $meta = is_array($r->meta ?? null) ? $r->meta : [];

                return [
                    'id' => $r->id,
                    'tenant_id' => $r->tenant_id,
                    'tenant_key' => $r->tenant_id ? ($tenantKeys[$r->tenant_id] ?? null) : null,
                    'action' => $r->action,
                    'status' => $r->status,
                    'started_at' => (string) $r->started_at,
                    'finished_at' => $r->finished_at ? (string) $r->finished_at : null,
                    'duration_ms' => $r->duration_ms,
                    'batch_id' => $meta['batch_id'] ?? null,
                    'meta' => $meta,
                ];
            });

            $this->line(json_encode([
                'ok' => true,
                'count' => $payload->count(),
                'data' => $payload,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $rows = $runs->map(function ($r) use ($tenantKeys) {
            $meta = is_array($r->meta ?? null) ? $r->meta : [];

            return [
                'id' => $r->id,
                'tenant' => $r->tenant_id ? (($tenantKeys[$r->tenant_id] ?? 'id='.$r->tenant_id)) : '-',
                'action' => $r->action,
                'status' => $r->status,
                'started_at' => $r->started_at ? $r->started_at->format('Y-m-d H:i:s') : null,
                'finished_at' => $r->finished_at ? $r->finished_at->format('Y-m-d H:i:s') : null,
                'duration_ms' => $r->duration_ms,
                'batch_id' => $meta['batch_id'] ?? null,
            ];
        })->all();

        if (empty($rows)) {
            $this->info('No runs found.');
            return self::SUCCESS;
        }

        $this->table(
            ['id', 'tenant', 'action', 'status', 'started_at', 'finished_at', 'duration_ms', 'batch_id'],
            $rows
        );

        return self::SUCCESS;
    }
}
