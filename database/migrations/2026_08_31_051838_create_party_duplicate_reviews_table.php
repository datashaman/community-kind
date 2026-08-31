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
        Schema::create('party_duplicate_reviews', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->uuid('intake_request_id');
            $table->foreignId('submitted_party_id');
            $table->foreignId('candidate_party_id');
            $table->json('matched_fields');
            $table->string('decision', 32)->default('pending');
            $table->foreignId('canonical_party_id')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->unsignedBigInteger('decided_by_user_id')->nullable()->index();
            $table->unsignedBigInteger('reversed_by_user_id')->nullable()->index();

            $table->foreign(['organisation_id', 'intake_request_id'])
                ->references(['organisation_id', 'id'])->on('intake_requests')->cascadeOnDelete();
            $table->foreign(['organisation_id', 'submitted_party_id'], 'duplicate_reviews_submitted_party_foreign')
                ->references(['organisation_id', 'id'])->on('parties')->restrictOnDelete();
            $table->foreign(['organisation_id', 'candidate_party_id'], 'duplicate_reviews_candidate_party_foreign')
                ->references(['organisation_id', 'id'])->on('parties')->restrictOnDelete();
            $table->foreign(['organisation_id', 'canonical_party_id'], 'duplicate_reviews_canonical_party_foreign')
                ->references(['organisation_id', 'id'])->on('parties')->restrictOnDelete();
            $table->unique(['organisation_id', 'intake_request_id', 'candidate_party_id']);
            $table->index(['organisation_id', 'decision']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('party_duplicate_reviews');
    }
};
