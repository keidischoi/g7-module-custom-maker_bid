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
            if (! Schema::hasColumn('maker_jobs', 'view_count')) {
                $table->unsignedInteger('view_count')->default(0)->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('maker_jobs') || ! Schema::hasColumn('maker_jobs', 'view_count')) {
            return;
        }
        Schema::table('maker_jobs', function (Blueprint $table) {
            $table->dropColumn('view_count');
        });
    }
};
