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
        Schema::create('service_cases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->uuid('intake_request_id');
            $table->foreignId('program_id');
            $table->foreignId('party_id');
            $table->string('status', 32)->default('open');
            $table->string('confidentiality', 32)->default('confidential');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('opened_at');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(['organisation_id', 'intake_request_id'])
                ->references(['organisation_id', 'id'])->on('intake_requests')->restrictOnDelete();
            $table->foreign(['organisation_id', 'program_id'])
                ->references(['organisation_id', 'id'])->on('programs')->restrictOnDelete();
            $table->foreign(['organisation_id', 'party_id'])
                ->references(['organisation_id', 'id'])->on('parties')->restrictOnDelete();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'intake_request_id']);
            $table->index(['organisation_id', 'program_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_cases');
    }
};
