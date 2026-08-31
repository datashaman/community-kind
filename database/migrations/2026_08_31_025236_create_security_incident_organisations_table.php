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
        Schema::create('security_incident_organisations', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('incident_uuid')->constrained('security_incidents')->cascadeOnDelete();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('impact_status');
            $table->text('impact_summary')->nullable();
            $table->text('approved_communication')->nullable();
            $table->timestamps();

            $table->unique(['incident_uuid', 'organisation_id']);
            $table->index(['organisation_id', 'impact_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_incident_organisations');
    }
};
