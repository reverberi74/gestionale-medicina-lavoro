<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();

            $table->string('code', 80)->unique(); // monthly_basic, yearly_basic, etc.
            $table->string('name', 190);
            $table->enum('billing_period', ['monthly', 'yearly']);
            $table->unsignedInteger('price_cents')->default(0);
            $table->char('currency', 3)->default('EUR');
            $table->boolean('is_active')->default(true);

            // Feature gating (future proof)
            $table->json('features')->nullable();
            $table->json('limits')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
