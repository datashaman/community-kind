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
        Schema::create('organisation_configurations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('area', 40);
            $table->string('configuration_key', 100);
            $table->unsignedInteger('version');
            $table->json('definition');
            $table->string('status', 24)->default('draft');
            $table->uuid('supersedes_id')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('activated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'area', 'configuration_key', 'version'], 'org_configs_area_key_version_unique');
            $table->index(['organisation_id', 'area', 'configuration_key', 'status'], 'org_configs_area_key_status_index');
        });
        Schema::table('organisation_configurations', function (Blueprint $table) {
            $table->foreign('supersedes_id')->references('id')->on('organisation_configurations')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organisation_configurations');
    }
};
