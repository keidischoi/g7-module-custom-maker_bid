<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('maker_payments')) {
            Schema::create('maker_payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('job_id');
                $table->unsignedBigInteger('bid_id')->nullable();
                $table->unsignedBigInteger('payer_user_id');
                $table->unsignedBigInteger('payee_user_id')->nullable();
                $table->unsignedInteger('amount')->default(0);
                $table->string('method', 32)->default('bank_transfer');
                $table->string('status', 24)->default('due');
                $table->string('destination', 24)->default('platform');
                $table->string('bank_name', 80)->nullable();
                $table->string('account_no', 80)->nullable();
                $table->string('account_holder', 80)->nullable();
                $table->string('transfer_note', 120)->nullable();
                $table->text('instructions')->nullable();
                $table->string('depositor_name', 80)->nullable();
                $table->string('memo', 500)->nullable();
                $table->timestamp('reported_at')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->unsignedBigInteger('confirmed_by')->nullable();
                $table->timestamp('refunded_at')->nullable();
                $table->string('refund_note', 500)->nullable();
                $table->timestamps();
                $table->unique('job_id');
                $table->index(['payer_user_id', 'status']);
                $table->index(['payee_user_id', 'status']);
                $table->index('status');
            });
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
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('maker_payments');
    }
};
