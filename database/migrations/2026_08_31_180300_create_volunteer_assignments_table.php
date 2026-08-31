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
        Schema::create('volunteer_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->uuid('volunteer_shift_id');
            $table->uuid('volunteer_application_id');
            $table->foreignId('party_id');
            $table->string('status', 32)->default('confirmed');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('confirmed_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('attended_at')->nullable();
            $table->foreignId('transitioned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(['organisation_id', 'volunteer_shift_id'], 'volunteer_assignments_shift_foreign')->references(['organisation_id', 'id'])->on('volunteer_shifts')->restrictOnDelete();
            $table->foreign(['organisation_id', 'volunteer_application_id'], 'volunteer_assignments_application_foreign')->references(['organisation_id', 'id'])->on('volunteer_applications')->restrictOnDelete();
            $table->foreign(['organisation_id', 'party_id'], 'volunteer_assignments_party_foreign')->references(['organisation_id', 'id'])->on('parties')->restrictOnDelete();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'volunteer_shift_id', 'party_id']);
            $table->index(['organisation_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('volunteer_assignments');
    }
};
