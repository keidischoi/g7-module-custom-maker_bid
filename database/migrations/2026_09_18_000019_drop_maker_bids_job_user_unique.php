<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('maker_bids')) {
            return;
        }
        Schema::table('maker_bids', function (Blueprint $table) {
            try {
                $table->dropUnique('maker_bids_job_user_unique');
            } catch (\Throwable) {
            }
        });
    }

    public function down(): void {}
};
