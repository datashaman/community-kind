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
        Schema::create('billing_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('billing_account_id')->constrained()->restrictOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('email');
            $table->string('role', 24);
            $table->boolean('offers_ownership')->default(false);
            $table->foreignId('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['billing_account_id', 'email', 'accepted_at', 'revoked_at'], 'billing_invites_pending_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_invitations');
    }
};
