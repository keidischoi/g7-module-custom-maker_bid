<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('maker_jobs')) {
            return;
        }

        if (Schema::hasColumn('maker_jobs', 'rush_deadline')) {
            return;
        }

        Schema::table('maker_jobs', function (Blueprint $table) {
            if (Schema::hasColumn('maker_jobs', 'rush_fee_enabled')) {
                $table->timestamp('rush_deadline')->nullable()->after('rush_fee_enabled');
            } else {
                $table->timestamp('rush_deadline')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Additive 0.5.2 safety net. Column may pre-exist from 0.5.0; do not drop.
    }
};
