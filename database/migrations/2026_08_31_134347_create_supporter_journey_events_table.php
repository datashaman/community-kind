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
        Schema::create('supporter_journey_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('supporter_journey_recipient_id')->constrained()->cascadeOnDelete();
            $table->uuid('idempotency_key');
            $table->string('type', 32);
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->json('metadata')->nullable();
            $table->timestampTz('occurred_at');
            $table->unique(['organisation_id', 'idempotency_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supporter_journey_events');
    }
};
