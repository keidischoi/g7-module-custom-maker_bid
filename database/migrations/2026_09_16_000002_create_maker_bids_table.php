<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maker_bids', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('job_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('amount');
            $table->unsignedInteger('days')->nullable();
            $table->text('message')->nullable();
            $table->string('status', 32)->default('pending'); // pending, accepted, rejected
            $table->timestamps();
            $table->index(['job_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maker_bids');
    }
};
