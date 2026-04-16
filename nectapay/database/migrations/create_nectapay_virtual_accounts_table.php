<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nectapay_virtual_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('owner_id');
            $table->string('owner_type')->nullable();
            $table->string('provider', 30)->default('nectapay');
            $table->string('account_name');
            $table->string('account_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('payment_ref')->unique();
            $table->string('provider_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->json('provider_response')->nullable();
            $table->timestamps();

            $table->unique(['owner_id', 'owner_type', 'provider']);
            $table->index('account_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nectapay_virtual_accounts');
    }
};
