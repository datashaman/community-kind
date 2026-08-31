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
        Schema::create('volunteer_credentials', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->uuid('volunteer_application_id');
            $table->foreignId('party_id');
            $table->string('type', 100);
            $table->string('status', 32)->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(['organisation_id', 'volunteer_application_id'], 'volunteer_credentials_application_foreign')->references(['organisation_id', 'id'])->on('volunteer_applications')->cascadeOnDelete();
            $table->foreign(['organisation_id', 'party_id'], 'volunteer_credentials_party_foreign')->references(['organisation_id', 'id'])->on('parties')->restrictOnDelete();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'volunteer_application_id', 'type']);
            $table->index(['organisation_id', 'status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('volunteer_credentials');
    }
};
