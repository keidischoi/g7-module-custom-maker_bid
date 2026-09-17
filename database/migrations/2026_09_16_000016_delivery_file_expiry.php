<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('maker_job_files')) {
            return;
        }
        Schema::table('maker_job_files', function (Blueprint $table) {
            if (! Schema::hasColumn('maker_job_files', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->index();
            }
            if (! Schema::hasColumn('maker_job_files', 'purged_at')) {
                $table->timestamp('purged_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('maker_job_files')) {
            return;
        }
        Schema::table('maker_job_files', function (Blueprint $table) {
            if (Schema::hasColumn('maker_job_files', 'purged_at')) {
                $table->dropColumn('purged_at');
            }
            if (Schema::hasColumn('maker_job_files', 'expires_at')) {
                $table->dropColumn('expires_at');
            }
        });
    }
};
