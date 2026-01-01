<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantOperationRun;
use Illuminate\Support\Str;
use Throwable;

class TenantOperationRunService
{
    // hard limits per non intasare meta/output
    private const MAX_STRING = 2000;
    private const MAX_OUTPUT = 4000;

    /**
     * Nota timing:
     * - started_at/finished_at in DB sono al secondo (tipicamente), quindi diffInMilliseconds può dare 0/1s “strani”.
     * - per duration_ms usiamo timestamps in ms (microtime) salvati in meta.
     */
    public function start(?Tenant $tenant, string $action, array $meta = [], ?int $triggeredByUserId = null): TenantOperationRun
    {
        $now = now();

        $meta = array_merge([
            // precisione ms per calcolare duration_ms senza dipendere dalla precisione dei datetime DB
            'started_ts_ms' => (int) round(microtime(true) * 1000),
        ], $meta);

        return TenantOperationRun::query()->create([
            'tenant_id' => $tenant?->id,
            'action' => $action,
            'status' => 'started',
            'started_at' => $now,
            'finished_at' => null,
            'duration_ms' => null,
            'triggered_by_user_id' => $triggeredByUserId,
            'meta' => $this->sanitizeMeta($meta),
        ]);
    }

    public function markSuccess(TenantOperationRun $run, array $meta = []): TenantOperationRun
    {
        return $this->finish($run, 'success', $meta, null);
    }

    public function markFailed(TenantOperationRun $run, Throwable $e, array $meta = []): TenantOperationRun
    {
        $base = [
            'exception_class' => get_class($e),
            'exception_message' => $this->truncate((string) $e->getMessage(), self::MAX_STRING),
        ];

        return $this->finish($run, 'failed', array_merge($base, $meta), $e);
    }

    private function finish(TenantOperationRun $run, string $status, array $meta, ?Throwable $e): TenantOperationRun
    {
        $finishedAt = now();
        $finishedTsMs = (int) round(microtime(true) * 1000);

        $currentMeta = is_array($run->meta ?? null) ? $run->meta : [];
        $merged = array_merge($currentMeta, $this->sanitizeMeta($meta));

        // duration_ms: preferiamo started_ts_ms (ms precision), fallback su Carbon diff
        $durationMs = null;

        $startedTsMs = $merged['started_ts_ms'] ?? ($currentMeta['started_ts_ms'] ?? null);
        if (is_numeric($startedTsMs)) {
            $durationMs = max(0, (int) ($finishedTsMs - (int) $startedTsMs));
        } elseif ($run->started_at) {
            $durationMs = $run->started_at->diffInMilliseconds($finishedAt);
        }

        // utile per audit/debug
        $merged['finished_ts_ms'] = $finishedTsMs;

        $run->forceFill([
            'status' => $status,
            'finished_at' => $finishedAt,
            'duration_ms' => $durationMs,
            'meta' => $merged,
        ])->save();

        return $run->refresh();
    }

    private function sanitizeMeta(array $meta): array
    {
        $out = [];

        foreach ($meta as $k => $v) {
            $key = is_string($k) ? Str::limit($k, 80, '') : (string) $k;

            if (is_null($v) || is_bool($v) || is_int($v) || is_float($v)) {
                $out[$key] = $v;
                continue;
            }

            if (is_string($v)) {
                $out[$key] = $this->truncate($v, self::MAX_STRING);
                continue;
            }

            if (is_array($v)) {
                $out[$key] = $this->sanitizeMeta($v);
                continue;
            }

            // fallback safe
            $out[$key] = $this->truncate((string) $v, self::MAX_STRING);
        }

        // campi “standard” dove spesso finisce output Artisan (più lungo)
        foreach (['output', 'artisan_output'] as $maybeOutputKey) {
            if (isset($out[$maybeOutputKey]) && is_string($out[$maybeOutputKey])) {
                $out[$maybeOutputKey] = $this->truncate($out[$maybeOutputKey], self::MAX_OUTPUT);
            }
        }

        return $out;
    }

    private function truncate(string $value, int $max): string
    {
        $value = trim($value);

        if (mb_strlen($value) <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max) . '…(truncated)';
    }
}
