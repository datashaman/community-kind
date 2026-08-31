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
        Schema::create('intake_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id');
            $table->foreignId('party_id');
            $table->string('type', 32)->default('content');
            $table->text('encrypted_content');
            $table->string('data_key_version', 64);
            $table->json('eligibility_context')->default('{}');
            $table->string('eligibility_status', 32)->default('needs_review');
            $table->string('status', 32)->default('draft');
            $table->string('urgency', 32)->default('routine');
            $table->json('risk_flags')->default('[]');
            $table->unsignedInteger('version')->default(1);
            $table->string('source', 100);
            $table->string('idempotency_key', 100)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(['organisation_id', 'program_id'])
                ->references(['organisation_id', 'id'])->on('programs')->restrictOnDelete();
            $table->foreign(['organisation_id', 'party_id'])
                ->references(['organisation_id', 'id'])->on('parties')->restrictOnDelete();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'idempotency_key']);
            $table->index(['organisation_id', 'program_id', 'status', 'urgency']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('intake_requests');
    }
};
