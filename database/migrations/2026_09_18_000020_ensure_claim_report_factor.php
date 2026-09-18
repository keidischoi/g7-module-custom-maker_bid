<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('maker_claims') && ! Schema::hasColumn('maker_claims', 'factor')) {
            Schema::table('maker_claims', function (Blueprint $table) {
                $table->string('factor', 32)->default('other')->after('user_id');
            });
        }
        if (Schema::hasTable('maker_reports') && ! Schema::hasColumn('maker_reports', 'factor')) {
            Schema::table('maker_reports', function (Blueprint $table) {
                $table->string('factor', 32)->default('other')->after('user_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('maker_claims') && Schema::hasColumn('maker_claims', 'factor')) {
            Schema::table('maker_claims', function (Blueprint $table) {
                $table->dropColumn('factor');
            });
        }
        if (Schema::hasTable('maker_reports') && Schema::hasColumn('maker_reports', 'factor')) {
            Schema::table('maker_reports', function (Blueprint $table) {
                $table->dropColumn('factor');
            });
        }
    }
};
