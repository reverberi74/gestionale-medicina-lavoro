<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RegistryDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // Create demo tenant "acme"
            DB::table('tenants')->updateOrInsert(
                ['key' => 'acme'],
                [
                    'name' => 'Acme Demo',
                    'db_name' => 'gmdl_tenant_demo',
                    'status' => 'active',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            /** @var Tenant $tenant */
            $tenant = Tenant::query()->where('key', 'acme')->firstOrFail();

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

            // Demo tenant admin user (dev only)
            User::updateOrCreate(
                ['email' => 'acme.admin@gmdl.test'],
                [
                    'name' => 'Acme Admin',
                    'password' => 'Password123', // cast hashed sul model
                    'role' => 'tenant_admin',
                    'tenant_id' => $tenant->id,
                    'is_active' => true,
                ]
            );

            // Ensure at least one plan exists (monthly_basic)
            $plan = Plan::query()->where('code', 'monthly_basic')->first();
            if (! $plan) {
                // fallback minimale (in caso non esegui RegistryPlansSeeder)
                $plan = Plan::create([
                    'code' => 'monthly_basic',
                    'name' => 'Basic — Monthly',
                    'billing_period' => 'monthly',
                    'price_cents' => 4900,
                    'currency' => 'EUR',
                    'is_active' => true,
                ]);
            }

            // Idempotenza: se c’è già una subscription corrente, non ricreare
            $tenant->load('currentSubscription');
            if ($tenant->currentSubscription) {
                return;
            }

            $trialDays = (int) config('billing.trial_days', 14);

            $sub = Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => 'trial',
                'current_period_start_at' => now(),
                'current_period_end_at' => now()->addDays($trialDays),
                'cancel_at_period_end' => false,
                'provider' => 'manual',
                'provider_ref' => null,
                'meta' => ['seed' => true],
            ]);

            $tenant->current_subscription_id = $sub->id;
            $tenant->save();
        });
    }
}
