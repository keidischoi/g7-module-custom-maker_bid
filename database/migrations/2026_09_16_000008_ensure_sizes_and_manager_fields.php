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

        if (! Schema::hasColumn('maker_jobs', 'sizes')) {
            Schema::table('maker_jobs', function (Blueprint $table) {
                if (Schema::hasColumn('maker_jobs', 'size_h')) {
                    $table->json('sizes')->nullable()->after('size_h');
                } else {
                    $table->json('sizes')->nullable();
                }
            });
        }

        Schema::table('maker_jobs', function (Blueprint $table) {
            if (! Schema::hasColumn('maker_jobs', 'manager_name')) {
                $table->string('manager_name', 120)->nullable();
            }
            if (! Schema::hasColumn('maker_jobs', 'manager_phone')) {
                $table->string('manager_phone', 40)->nullable();
            }
            if (! Schema::hasColumn('maker_jobs', 'manager_email')) {
                $table->string('manager_email', 120)->nullable();
            }
        });
    }

    public function down(): void
    {
        // Additive 0.5.2 safety net. Do not drop.
    }
};
