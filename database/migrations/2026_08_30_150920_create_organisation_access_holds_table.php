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
        Schema::create('organisation_access_holds', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issuer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('issuer');
            $table->text('reason');
            $table->string('scope');
            $table->string('access_level');
            $table->uuid('incident_uuid')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('review_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('released_by')->nullable();
            $table->text('release_reason')->nullable();
            $table->timestamps();

            $table->index(['organisation_id', 'starts_at', 'expires_at'], 'organisation_access_holds_active_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organisation_access_holds');
    }
};
