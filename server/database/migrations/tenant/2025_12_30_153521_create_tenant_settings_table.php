<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('tenant')->create('tenant_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique();
            $table->json('value')->nullable();
            $table->timestamps();

            // Nota: unique(key) è già indice. Nessun altro indice serve per ora.
        });
    }

    public function down(): void
    {
        Schema::connection('tenant')->dropIfExists('tenant_settings');
    }
};
