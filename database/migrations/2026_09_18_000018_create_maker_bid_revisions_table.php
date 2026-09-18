<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('maker_bid_revisions')) {
            return;
        }
        Schema::create('maker_bid_revisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bid_id');
            $table->unsignedBigInteger('job_id');
            $table->unsignedBigInteger('user_id');
            $table->string('event', 16)->default('update');
            $table->unsignedInteger('amount');
            $table->unsignedInteger('days')->nullable();
            $table->text('message')->nullable();
            $table->string('status', 32)->nullable();
            $table->timestamps();
            $table->index(['bid_id', 'created_at']);
            $table->index(['job_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maker_bid_revisions');
    }
};
