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
            if (! Schema::hasColumn('maker_jobs', 'refund_bank_name')) {
                $table->string('refund_bank_name', 80)->nullable();
            }
            if (! Schema::hasColumn('maker_jobs', 'refund_account_holder')) {
                $table->string('refund_account_holder', 80)->nullable();
            }
            if (! Schema::hasColumn('maker_jobs', 'refund_account_no')) {
                $table->string('refund_account_no', 80)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('maker_jobs')) {
            return;
        }
        Schema::table('maker_jobs', function (Blueprint $table) {
            foreach (['refund_bank_name', 'refund_account_holder', 'refund_account_no'] as $col) {
                if (Schema::hasColumn('maker_jobs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
