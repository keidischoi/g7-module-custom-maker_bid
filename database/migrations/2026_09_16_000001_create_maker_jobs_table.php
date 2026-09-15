<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maker_jobs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('type', 32); // print_3d, design, manufacture
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('budget')->nullable();
            $table->string('status', 32)->default('open'); // open, awarded, done, cancelled
            $table->unsignedBigInteger('awarded_bid_id')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maker_jobs');
    }
};
