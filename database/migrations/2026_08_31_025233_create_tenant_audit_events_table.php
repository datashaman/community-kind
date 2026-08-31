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
        Schema::create('tenant_audit_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->string('type');
            $table->unsignedSmallInteger('schema_version');
            $table->string('subject_type');
            $table->string('subject_id');
            $table->json('payload');
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['organisation_id', 'occurred_at', 'id']);
            $table->index(['organisation_id', 'subject_type', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_audit_events');
    }
};
