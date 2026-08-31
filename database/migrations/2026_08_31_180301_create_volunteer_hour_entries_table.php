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
        Schema::create('volunteer_hour_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->uuid('volunteer_assignment_id');
            $table->foreignId('party_id');
            $table->unsignedInteger('minutes');
            $table->timestamp('occurred_at');
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(['organisation_id', 'volunteer_assignment_id'], 'volunteer_hours_assignment_foreign')->references(['organisation_id', 'id'])->on('volunteer_assignments')->restrictOnDelete();
            $table->foreign(['organisation_id', 'party_id'], 'volunteer_hours_party_foreign')->references(['organisation_id', 'id'])->on('parties')->restrictOnDelete();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'volunteer_assignment_id']);
            $table->index(['organisation_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('volunteer_hour_entries');
    }
};
