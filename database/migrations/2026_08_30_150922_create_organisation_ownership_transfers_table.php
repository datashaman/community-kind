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
        Schema::create('organisation_ownership_transfers', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('nominated_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('nominee_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['organisation_id', 'status']);
            $table->index(['nominee_user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organisation_ownership_transfers');
    }
};
