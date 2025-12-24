<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class RegistryPlansSeeder extends Seeder
{
    public function run(): void
    {
        Plan::updateOrCreate(
            ['code' => 'monthly_basic'],
            [
                'name' => 'Basic — Monthly',
                'billing_period' => 'monthly',
                'price_cents' => 4900,
                'currency' => 'EUR',
                'is_active' => true,
                'features' => [
                    'allegato_3b' => true,
                    'portal' => false,
                    'signing' => false,
                    'advanced_reports' => false,
                ],
                'limits' => [
                    'max_companies' => 999999,
                    'max_workers' => 999999,
                    'max_users' => 1,
                ],
            ]
        );

        Plan::updateOrCreate(
            ['code' => 'yearly_basic'],
            [
                'name' => 'Basic — Yearly',
                'billing_period' => 'yearly',
                'price_cents' => 49000,
                'currency' => 'EUR',
                'is_active' => true,
                'features' => [
                    'allegato_3b' => true,
                    'portal' => false,
                    'signing' => false,
                    'advanced_reports' => false,
                ],
                'limits' => [
                    'max_companies' => 999999,
                    'max_workers' => 999999,
                    'max_users' => 1,
                ],
            ]
        );
    }
}
