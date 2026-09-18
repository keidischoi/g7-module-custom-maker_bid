<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('maker_reports')) {
            return;
        }
        if (! Schema::hasColumn('maker_reports', 'company_id')) {
            Schema::table('maker_reports', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->after('job_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('maker_reports') && Schema::hasColumn('maker_reports', 'company_id')) {
            Schema::table('maker_reports', function (Blueprint $table) {
                $table->dropColumn('company_id');
            });
        }
    }
};
