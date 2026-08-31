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
        Schema::create('partner_commitments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->uuid('partner_profile_id');
            $table->string('title');
            $table->text('details');
            $table->string('status', 32)->default('planned');
            $table->date('due_on')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->foreign(['organisation_id', 'partner_profile_id'])->references(['organisation_id', 'id'])->on('partner_profiles')->cascadeOnDelete();
            $table->unique(['organisation_id', 'id']);
            $table->index(['organisation_id', 'status', 'due_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_commitments');
    }
};
