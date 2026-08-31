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
        Schema::create('case_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->uuid('service_case_id');
            $table->string('type', 32)->default('case_document_name');
            $table->string('classification', 32);
            $table->text('encrypted_display_name');
            $table->unsignedInteger('generation')->default(0);
            $table->uuid('current_version_id')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(['organisation_id', 'service_case_id'])
                ->references(['organisation_id', 'id'])->on('service_cases')->restrictOnDelete();
            $table->unique(['organisation_id', 'id']);
            $table->index(['organisation_id', 'service_case_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_documents');
    }
};
