<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // ✅ garantiamo che operi sempre sul control-plane
    protected $connection = 'registry';

    public function up(): void
    {
        // 1) Se esistono tenant creati con status='trial', li portiamo a 'active'
        //    (operational status deve essere 'active'; la trial sta in subscriptions.status)
        DB::connection('registry')
            ->table('tenants')
            ->where('status', 'trial')
            ->update(['status' => 'active']);

        // 2) Allineiamo il DEFAULT del campo (evitiamo ->change() per non richiedere doctrine/dbal)
        DB::connection('registry')->statement(
            "ALTER TABLE tenants MODIFY status VARCHAR(32) NOT NULL DEFAULT 'active'"
        );
    }

    public function down(): void
    {
        // Revert del DEFAULT (non tocchiamo i dati per evitare rollback distruttivi)
        DB::connection('registry')->statement(
            "ALTER TABLE tenants MODIFY status VARCHAR(32) NOT NULL DEFAULT 'trial'"
        );
    }
};
