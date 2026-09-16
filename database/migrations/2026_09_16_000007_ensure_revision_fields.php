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

        if (! Schema::hasColumn('maker_jobs', 'revision_enabled')) {
            Schema::table('maker_jobs', function (Blueprint $table) {
                $table->boolean('revision_enabled')->default(false);
            });
        }
        if (! Schema::hasColumn('maker_jobs', 'revision_count')) {
            Schema::table('maker_jobs', function (Blueprint $table) {
                $table->unsignedTinyInteger('revision_count')->nullable();
            });
        }
        if (! Schema::hasColumn('maker_jobs', 'revision_cost')) {
            Schema::table('maker_jobs', function (Blueprint $table) {
                $table->unsignedInteger('revision_cost')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Additive 0.5.2 safety net. Columns may pre-exist from 0.5.0; do not drop.
    }
};
