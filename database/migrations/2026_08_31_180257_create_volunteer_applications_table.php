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
        Schema::create('volunteer_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->uuid('volunteer_opportunity_id');
            $table->foreignId('party_id');
            $table->uuid('supporter_registration_id');
            $table->string('status', 32)->default('submitted');
            $table->string('onboarding_status', 32)->default('not_started');
            $table->json('interests')->default('[]');
            $table->json('availability')->default('[]');
            $table->string('follow_up_status', 32)->default('suppressed');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(['organisation_id', 'volunteer_opportunity_id'], 'volunteer_applications_opportunity_foreign')->references(['organisation_id', 'id'])->on('volunteer_opportunities')->restrictOnDelete();
            $table->foreign(['organisation_id', 'party_id'], 'volunteer_applications_party_foreign')->references(['organisation_id', 'id'])->on('parties')->restrictOnDelete();
            $table->foreign(['organisation_id', 'supporter_registration_id'], 'volunteer_applications_registration_foreign')->references(['organisation_id', 'id'])->on('supporter_registrations')->restrictOnDelete();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'supporter_registration_id']);
            $table->unique(['organisation_id', 'volunteer_opportunity_id', 'party_id']);
            $table->index(['organisation_id', 'status', 'onboarding_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('volunteer_applications');
    }
};
