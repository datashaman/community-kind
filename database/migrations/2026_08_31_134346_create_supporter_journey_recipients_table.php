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
        Schema::create('supporter_journey_recipients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('supporter_journey_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id')->constrained()->restrictOnDelete();
            $table->string('status', 24)->default('queued');
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestampTz('last_attempted_at')->nullable();
            $table->timestamps();
            $table->unique(['supporter_journey_id', 'party_id']);
            $table->index(['organisation_id', 'party_id', 'status', 'updated_at'], 'journey_recipient_frequency_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supporter_journey_recipients');
    }
};
