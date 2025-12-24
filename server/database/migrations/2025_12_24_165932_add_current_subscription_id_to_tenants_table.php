<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedBigInteger('current_subscription_id')->nullable()->after('status');

            $table->foreign('current_subscription_id')
                ->references('id')->on('subscriptions')
                ->nullOnDelete();

            $table->index('current_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['current_subscription_id']);
            $table->dropIndex(['current_subscription_id']);
            $table->dropColumn('current_subscription_id');
        });
    }
};
