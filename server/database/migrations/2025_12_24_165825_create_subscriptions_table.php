<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('plan_id');

            $table->enum('status', ['trial', 'active', 'past_due', 'canceled', 'suspended', 'expired'])
                ->default('trial');

            $table->timestamp('current_period_start_at')->nullable();
            $table->timestamp('current_period_end_at')->nullable();

            $table->boolean('cancel_at_period_end')->default(false);

            $table->enum('provider', ['manual', 'stripe', 'paypal'])->default('manual');
            $table->string('provider_ref', 190)->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')->on('tenants')
                ->onDelete('cascade');

            $table->foreign('plan_id')
                ->references('id')->on('plans')
                ->onDelete('restrict');

            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'current_period_end_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
