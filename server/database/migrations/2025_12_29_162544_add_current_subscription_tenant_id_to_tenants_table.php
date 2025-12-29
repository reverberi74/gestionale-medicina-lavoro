<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // sempre sul control-plane
    protected $connection = 'registry';

    public function up(): void
    {
        // 1) aggiungiamo colonna shadow
        Schema::connection('registry')->table('tenants', function (Blueprint $table) {
            $table->unsignedBigInteger('current_subscription_tenant_id')
                ->nullable()
                ->after('current_subscription_id');

            $table->index(
                ['current_subscription_id', 'current_subscription_tenant_id'],
                'tenants_current_sub_pair_index'
            );
        });

        // 2) backfill: per i tenant già settati, allineiamo la shadow col PK tenant
        DB::connection('registry')->statement("
            UPDATE tenants
            SET current_subscription_tenant_id = id
            WHERE current_subscription_id IS NOT NULL
        ");

        // 3) rendiamo referenziabile (id, tenant_id) su subscriptions
        Schema::connection('registry')->table('subscriptions', function (Blueprint $table) {
            // Indice/unique “di sicurezza” per FK composita (id è già PK, ma così MySQL è 100% contento)
            $table->unique(['id', 'tenant_id'], 'subscriptions_id_tenant_id_unique');
        });

        // 4) FK composita: se entrambi valorizzati, devono matchare (id, tenant_id)
        Schema::connection('registry')->table('tenants', function (Blueprint $table) {
            $table->foreign(
                ['current_subscription_id', 'current_subscription_tenant_id'],
                'tenants_current_subscription_pair_fk'
            )
                ->references(['id', 'tenant_id'])
                ->on('subscriptions')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::connection('registry')->table('tenants', function (Blueprint $table) {
            $table->dropForeign('tenants_current_subscription_pair_fk');
            $table->dropIndex('tenants_current_sub_pair_index');
            $table->dropColumn('current_subscription_tenant_id');
        });

        Schema::connection('registry')->table('subscriptions', function (Blueprint $table) {
            $table->dropUnique('subscriptions_id_tenant_id_unique');
        });
    }
};
