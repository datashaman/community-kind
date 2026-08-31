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
        Schema::create('event_registrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->uuid('community_event_id');
            $table->foreignId('party_id');
            $table->uuid('supporter_registration_id');
            $table->string('status', 32);
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('registered_at');
            $table->timestamp('reminded_at')->nullable();
            $table->timestamp('attended_at')->nullable();
            $table->timestamp('followed_up_at')->nullable();
            $table->foreignId('transitioned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign(['organisation_id', 'community_event_id'])->references(['organisation_id', 'id'])->on('community_events')->restrictOnDelete();
            $table->foreign(['organisation_id', 'party_id'])->references(['organisation_id', 'id'])->on('parties')->restrictOnDelete();
            $table->foreign(['organisation_id', 'supporter_registration_id'])->references(['organisation_id', 'id'])->on('supporter_registrations')->restrictOnDelete();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'community_event_id', 'party_id']);
            $table->unique(['organisation_id', 'supporter_registration_id']);
            $table->index(['organisation_id', 'status', 'registered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_registrations');
    }
};
