<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('maker_payments')) {
            Schema::table('maker_payments', function (Blueprint $table) {
                if (! Schema::hasColumn('maker_payments', 'kind')) {
                    $table->string('kind', 24)->default('full')->after('status');
                }
                if (! Schema::hasColumn('maker_payments', 'deposit_percent')) {
                    $table->unsignedTinyInteger('deposit_percent')->nullable();
                }
                if (! Schema::hasColumn('maker_payments', 'deposit_terms')) {
                    $table->string('deposit_terms', 500)->nullable();
                }
            });
            try {
                Schema::table('maker_payments', function (Blueprint $table) {
                    $table->dropUnique('maker_payments_job_id_unique');
                });
            } catch (\Throwable) {
            }
            try {
                Schema::table('maker_payments', function (Blueprint $table) {
                    $table->unique(['job_id', 'kind'], 'maker_payments_job_kind_unique');
                });
            } catch (\Throwable) {
            }
        }

        if (Schema::hasTable('maker_companies')) {
            Schema::table('maker_companies', function (Blueprint $table) {
                if (! Schema::hasColumn('maker_companies', 'bank_name')) {
                    $table->string('bank_name', 80)->nullable();
                }
                if (! Schema::hasColumn('maker_companies', 'account_no')) {
                    $table->string('account_no', 80)->nullable();
                }
                if (! Schema::hasColumn('maker_companies', 'account_holder')) {
                    $table->string('account_holder', 80)->nullable();
                }
                if (! Schema::hasColumn('maker_companies', 'deposit_percent')) {
                    $table->unsignedTinyInteger('deposit_percent')->nullable();
                }
                if (! Schema::hasColumn('maker_companies', 'deposit_terms')) {
                    $table->string('deposit_terms', 500)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('maker_payments')) {
            try {
                Schema::table('maker_payments', function (Blueprint $table) {
                    $table->dropUnique('maker_payments_job_kind_unique');
                });
            } catch (\Throwable) {
            }
        }
    }
};
