<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // tenant association (registry)
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            $table->string('role', 50)->default('operator')->after('password');
            $table->boolean('is_active')->default(true)->after('role');
            $table->timestamp('last_login_at')->nullable()->after('is_active');

            $table->index('tenant_id', 'users_tenant_id_idx');

            // FK verso registry.tenants
            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropIndex('users_tenant_id_idx');

            $table->dropColumn([
                'tenant_id',
                'role',
                'is_active',
                'last_login_at',
            ]);
        });
    }
};
