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
        Schema::create('security_incidents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('classification');
            $table->string('severity');
            $table->string('status');
            $table->string('detection_source');
            $table->text('summary');
            $table->timestamp('detected_at');
            $table->timestamp('first_awareness_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('commander_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'severity', 'detected_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_incidents');
    }
};
