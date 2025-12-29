<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'registry';

    public function up(): void
    {
        Schema::connection('registry')->table('audit_logs', function (Blueprint $table) {
            // FK: preserviamo i log anche se user/tenant vengono rimossi
            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->nullOnDelete();

            $table->foreign('user_id')
                ->references('id')->on('users')
                ->nullOnDelete();

            // indici “da query reali”
            $table->index(['tenant_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('registry')->table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropForeign(['user_id']);

            $table->dropIndex(['tenant_id', 'created_at']);
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['event', 'created_at']);
        });
    }
};
