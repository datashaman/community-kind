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
        Schema::create('audit_digest_manifests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->date('manifest_date')->unique();
            $table->unsignedInteger('event_count');
            $table->char('event_digest', 64);
            $table->char('previous_manifest_digest', 64)->nullable();
            $table->char('manifest_digest', 64)->unique();
            $table->char('signature', 64);
            $table->string('event_export_path');
            $table->string('manifest_path');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_digest_manifests');
    }
};
