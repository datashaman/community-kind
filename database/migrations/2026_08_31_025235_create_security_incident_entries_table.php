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
        Schema::create('security_incident_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUuid('incident_uuid')->constrained('security_incidents')->cascadeOnDelete();
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->string('type');
            $table->text('summary');
            $table->string('reference')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['incident_uuid', 'occurred_at', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_incident_entries');
    }
};
