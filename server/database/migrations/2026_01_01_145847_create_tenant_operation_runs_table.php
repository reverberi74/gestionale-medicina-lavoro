<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('registry')->create('tenant_operation_runs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('action', 40);   // provision|migrate|repair
            $table->string('status', 20);   // started|success|failed

            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();

            $table->unsignedBigInteger('triggered_by_user_id')->nullable();

            $table->json('meta')->nullable();

            $table->index(['tenant_id', 'started_at']);
            $table->index(['action', 'started_at']);
            $table->index(['status', 'started_at']);
            $table->index(['triggered_by_user_id', 'started_at']);

            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->nullOnDelete();

            $table->foreign('triggered_by_user_id')
                ->references('id')->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::connection('registry')->dropIfExists('tenant_operation_runs');
    }
};
