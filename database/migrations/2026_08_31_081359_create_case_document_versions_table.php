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
        Schema::create('case_document_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->uuid('case_document_id');
            $table->string('type', 48)->default('case_document_version_security');
            $table->unsignedInteger('generation');
            $table->string('classification', 32);
            $table->text('encrypted_display_name');
            $table->string('state', 32);
            $table->string('quarantine_path')->nullable();
            $table->string('object_key')->nullable();
            $table->string('detected_mime', 64)->nullable();
            $table->unsignedBigInteger('byte_size')->nullable();
            $table->text('encrypted_sha256')->nullable();
            $table->string('scanner_engine_version', 64)->nullable();
            $table->string('scanner_signature_version', 64)->nullable();
            $table->string('result_category', 64)->nullable();
            $table->timestamp('scan_started_at')->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign(['organisation_id', 'case_document_id'])
                ->references(['organisation_id', 'id'])->on('case_documents')->restrictOnDelete();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'case_document_id', 'id'], 'case_document_version_owner_unique');
            $table->unique(['organisation_id', 'case_document_id', 'generation'], 'case_document_generation_unique');
            $table->index(['organisation_id', 'state', 'created_at']);
        });

        Schema::table('case_documents', function (Blueprint $table) {
            $table->foreign(['organisation_id', 'id', 'current_version_id'], 'case_document_current_version_foreign')
                ->references(['organisation_id', 'case_document_id', 'id'])->on('case_document_versions')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('case_documents', function (Blueprint $table) {
            $table->dropForeign('case_document_current_version_foreign');
        });
        Schema::dropIfExists('case_document_versions');
    }
};
