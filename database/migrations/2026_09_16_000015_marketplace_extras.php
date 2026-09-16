<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maker_jobs', function (Blueprint $table) {
            if (! Schema::hasColumn('maker_jobs', 'terms_agreed')) {
                $table->boolean('terms_agreed')->default(false);
            }
            if (! Schema::hasColumn('maker_jobs', 'work_status')) {
                $table->string('work_status', 32)->nullable();
            }
            if (! Schema::hasColumn('maker_jobs', 'tracking_no')) {
                $table->string('tracking_no', 80)->nullable();
            }
            if (! Schema::hasColumn('maker_jobs', 'carrier')) {
                $table->string('carrier', 80)->nullable();
            }
        });

        if (! Schema::hasTable('maker_notices')) {
            Schema::create('maker_notices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('job_id')->nullable();
                $table->string('type', 40);
                $table->string('title', 200);
                $table->text('body')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'read_at']);
            });
        }
        if (! Schema::hasTable('maker_messages')) {
            Schema::create('maker_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('job_id');
                $table->unsignedBigInteger('user_id');
                $table->text('body');
                $table->timestamps();
                $table->index('job_id');
            });
        }
        if (! Schema::hasTable('maker_reviews')) {
            Schema::create('maker_reviews', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('job_id');
                $table->unsignedBigInteger('company_id')->nullable();
                $table->unsignedBigInteger('user_id');
                $table->unsignedTinyInteger('score');
                $table->string('comment', 2000)->nullable();
                $table->timestamps();
                $table->unique('job_id');
            });
        }
        if (! Schema::hasTable('maker_claims')) {
            Schema::create('maker_claims', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('job_id');
                $table->unsignedBigInteger('user_id');
                $table->string('reason', 2000);
                $table->string('status', 24)->default('open');
                $table->text('admin_note')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('maker_reports')) {
            Schema::create('maker_reports', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('job_id');
                $table->unsignedBigInteger('user_id');
                $table->string('reason', 2000);
                $table->string('status', 24)->default('open');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('maker_audits')) {
            Schema::create('maker_audits', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('action', 64);
                $table->string('subject_type', 40);
                $table->unsignedBigInteger('subject_id')->nullable();
                $table->text('meta')->nullable();
                $table->timestamps();
                $table->index(['subject_type', 'subject_id']);
            });
        }
        if (! Schema::hasTable('maker_file_logs')) {
            Schema::create('maker_file_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('hash', 80);
                $table->unsignedBigInteger('job_id')->nullable();
                $table->string('ip', 45)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('maker_file_logs');
        Schema::dropIfExists('maker_audits');
        Schema::dropIfExists('maker_reports');
        Schema::dropIfExists('maker_claims');
        Schema::dropIfExists('maker_reviews');
        Schema::dropIfExists('maker_messages');
        Schema::dropIfExists('maker_notices');
    }
};
