<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('registry')->create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();

            $table->string('event', 80)->index();
            $table->string('method', 10)->nullable();
            $table->string('path', 255)->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();

            $table->string('ip', 45)->nullable();
            $table->string('host', 255)->nullable();
            $table->text('user_agent')->nullable();

            $table->json('meta')->nullable();

            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::connection('registry')->dropIfExists('audit_logs');
    }
};
