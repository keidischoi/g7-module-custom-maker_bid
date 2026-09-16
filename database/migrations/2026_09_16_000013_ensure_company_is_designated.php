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
            if (! Schema::hasColumn('maker_companies', 'is_designated')) {
                $table->boolean('is_designated')->default(false)->after('is_recommended');
            }
        });
    }

    public function down(): void
    {
        // Additive 0.7.1 safety net. Do not drop.
    }
};
