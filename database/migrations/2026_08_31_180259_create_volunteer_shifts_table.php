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
        Schema::create('volunteer_shifts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->uuid('volunteer_opportunity_id');
            $table->string('title');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->unsignedInteger('capacity');
            $table->string('status', 32)->default('open');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(['organisation_id', 'volunteer_opportunity_id'], 'volunteer_shifts_opportunity_foreign')->references(['organisation_id', 'id'])->on('volunteer_opportunities')->restrictOnDelete();
            $table->unique(['organisation_id', 'id']);
            $table->index(['organisation_id', 'starts_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('volunteer_shifts');
    }
};
