<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('billing_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('organisation_id')->constrained()->restrictOnDelete();
            $table->string('status', 32)->default('pending_activation');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('current_marker')->nullable()->default(true);
            $table->timestamps();
            $table->unique(['organisation_id', 'current_marker'], 'subscriptions_one_current_per_org_unique');
            $table->index(['billing_account_id', 'status', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
