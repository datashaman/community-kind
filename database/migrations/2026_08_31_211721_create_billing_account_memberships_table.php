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
        Schema::create('billing_account_memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('billing_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('role', 24);
            $table->boolean('is_owner')->default(false);
            $table->timestamp('accepted_at');
            $table->timestamp('ended_at')->nullable();
            $table->boolean('active_marker')->nullable()->default(true);
            $table->timestamps();
            $table->unique(['billing_account_id', 'user_id', 'active_marker'], 'billing_memberships_one_active_unique');
            $table->index(['user_id', 'ended_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_account_memberships');
    }
};
