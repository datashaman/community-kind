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
        Schema::create('sandbox_pairs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('status')->index();
            $table->unsignedInteger('generation')->default(1);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('purged_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sandbox_bootstrap_tokens', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUuid('sandbox_pair_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->unsignedInteger('generation');
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sandbox_bootstrap_tokens');
        Schema::dropIfExists('sandbox_pairs');
    }
};
