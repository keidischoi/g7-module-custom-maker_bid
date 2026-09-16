<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('maker_module_settings')) {
            return;
        }

        Schema::create('maker_module_settings', function (Blueprint $table) {
            $table->id();
            $table->string('category', 64)->unique();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Additive table is left in place.
    }
};
