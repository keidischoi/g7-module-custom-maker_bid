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
            if (! Schema::hasColumn('maker_jobs', 'bidding_status')) {
                $table->string('bidding_status', 20)->default('open');
            }
            if (! Schema::hasColumn('maker_jobs', 'bidding_closed_at')) {
                $table->timestamp('bidding_closed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('maker_jobs')) {
            return;
        }
        Schema::table('maker_jobs', function (Blueprint $table) {
            if (Schema::hasColumn('maker_jobs', 'bidding_closed_at')) {
                $table->dropColumn('bidding_closed_at');
            }
            if (Schema::hasColumn('maker_jobs', 'bidding_status')) {
                $table->dropColumn('bidding_status');
            }
        });
    }
};
