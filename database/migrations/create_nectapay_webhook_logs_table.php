<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nectapay_webhook_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('transaction_id')->index();
            $table->string('account_number')->nullable()->index();
            $table->string('payment_ref')->nullable()->index();
            $table->decimal('amount_paid', 15, 2)->nullable();
            $table->string('status', 30)->default('received');
            $table->json('payload')->nullable();
            $table->json('raw_payload')->nullable();
            $table->json('amounts')->nullable();
            $table->json('hash_details')->nullable();
            $table->text('error_message')->nullable();
            $table->uuid('payment_id')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nectapay_webhook_logs');
    }
};
