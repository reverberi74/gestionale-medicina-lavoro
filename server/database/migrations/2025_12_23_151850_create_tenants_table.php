<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();

            // stable tenant identifier (subdomain key)
            $table->string('key', 64)->unique();

            $table->string('name', 150);

            // physical database name for this tenant
            $table->string('db_name', 128)->unique();

            // minimal subscription/status flag (we’ll evolve later)
            $table->string('status', 32)->default('trial');

            $table->timestamps();

            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
