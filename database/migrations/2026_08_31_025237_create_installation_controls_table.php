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
        Schema::create('installation_controls', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUuid('incident_uuid')->constrained('security_incidents')->cascadeOnDelete();
            $table->string('capability');
            $table->string('reason_code');
            $table->foreignId('activated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at');
            $table->foreignId('released_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->string('release_reason_code')->nullable();
            $table->timestamps();

            $table->index(['capability', 'released_at']);
            $table->index(['incident_uuid', 'released_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('installation_controls');
    }
};
