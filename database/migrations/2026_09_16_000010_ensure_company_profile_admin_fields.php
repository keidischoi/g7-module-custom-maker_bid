<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('maker_companies')) {
            return;
        }

        Schema::table('maker_companies', function (Blueprint $table) {
            if (! Schema::hasColumn('maker_companies', 'kind')) {
                $table->string('kind', 32)->default('company')->after('name');
            }
            if (! Schema::hasColumn('maker_companies', 'business_no')) {
                $table->string('business_no', 32)->nullable()->after('type');
            }
            if (! Schema::hasColumn('maker_companies', 'job_types')) {
                $table->json('job_types')->nullable()->after('business_no');
            }
            if (! Schema::hasColumn('maker_companies', 'logo_hash')) {
                $table->string('logo_hash', 32)->nullable()->after('job_types');
            }
            if (! Schema::hasColumn('maker_companies', 'bio')) {
                $table->text('bio')->nullable()->after('note');
            }
            if (! Schema::hasColumn('maker_companies', 'homepage_url')) {
                $table->string('homepage_url', 500)->nullable();
            }
            if (! Schema::hasColumn('maker_companies', 'portfolio_url')) {
                $table->string('portfolio_url', 500)->nullable();
            }
            if (! Schema::hasColumn('maker_companies', 'manager_name')) {
                $table->string('manager_name', 120)->nullable();
            }
            if (! Schema::hasColumn('maker_companies', 'phone')) {
                $table->string('phone', 40)->nullable();
            }
            if (! Schema::hasColumn('maker_companies', 'email')) {
                $table->string('email', 120)->nullable();
            }
            if (! Schema::hasColumn('maker_companies', 'zipcode')) {
                $table->string('zipcode', 20)->nullable();
            }
            if (! Schema::hasColumn('maker_companies', 'address')) {
                $table->string('address', 255)->nullable();
            }
            if (! Schema::hasColumn('maker_companies', 'address_detail')) {
                $table->string('address_detail', 255)->nullable();
            }
            if (! Schema::hasColumn('maker_companies', 'admin_memo')) {
                $table->text('admin_memo')->nullable();
            }
            if (! Schema::hasColumn('maker_companies', 'hold_reason')) {
                $table->string('hold_reason', 2000)->nullable();
            }
            if (! Schema::hasColumn('maker_companies', 'rating_score')) {
                $table->decimal('rating_score', 3, 2)->default(0);
            }
            if (! Schema::hasColumn('maker_companies', 'rating_count')) {
                $table->unsignedInteger('rating_count')->default(0);
            }
            if (! Schema::hasColumn('maker_companies', 'claim_count')) {
                $table->unsignedInteger('claim_count')->default(0);
            }
            if (! Schema::hasColumn('maker_companies', 'claim_history')) {
                $table->json('claim_history')->nullable();
            }
            if (! Schema::hasColumn('maker_companies', 'report_count')) {
                $table->unsignedInteger('report_count')->default(0);
            }
            if (! Schema::hasColumn('maker_companies', 'is_recommended')) {
                $table->boolean('is_recommended')->default(false);
            }
            if (! Schema::hasColumn('maker_companies', 'priority')) {
                $table->unsignedInteger('priority')->default(0);
            }
            if (! Schema::hasColumn('maker_companies', 'upload_token')) {
                $table->string('upload_token', 64)->nullable();
            }
        });
    }

    public function down(): void
    {
        // Additive 0.6.0 safety net. Do not drop.
    }
};
