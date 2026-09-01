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
        Schema::create('published_impact_snapshots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('audience', 24);
            $table->string('registry_version', 24);
            $table->json('metrics');
            $table->json('cohort_comparisons');
            $table->json('period');
            $table->json('filters');
            $table->timestamp('approved_at');
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'id']);
            $table->index(['organisation_id', 'audience', 'published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('published_impact_snapshots');
    }
};
