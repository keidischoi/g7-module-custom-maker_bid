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

        Schema::table('maker_jobs', function (Blueprint $table) {
            if (! Schema::hasColumn('maker_jobs', 'audience')) {
                $table->string('audience', 32)->default('all')->after('status');
            }
            if (! Schema::hasColumn('maker_jobs', 'ownership_requested')) {
                $after = Schema::hasColumn('maker_jobs', 'provided_extensions') ? 'provided_extensions' : 'status';
                $table->boolean('ownership_requested')->default(false)->after($after);
            }
        });
    }

    public function down(): void
    {
        // Additive columns are left in place.
    }
};
