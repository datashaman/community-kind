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
        Schema::create('platform_security_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('type');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('subject_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata');
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['type', 'occurred_at']);
            $table->index(['subject_user_id', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_security_events');
    }
};
