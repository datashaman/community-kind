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
        Schema::table('platform_security_events', function (Blueprint $table) {
            $table->dropForeign(['actor_user_id']);
            $table->dropForeign(['subject_user_id']);
            $table->unsignedSmallInteger('schema_version')->default(1)->after('type');
            $table->uuid('incident_uuid')->nullable()->after('schema_version');
            $table->foreign('incident_uuid')->references('id')->on('security_incidents')->nullOnDelete();
            $table->index(['incident_uuid', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('platform_security_events', function (Blueprint $table) {
            $table->dropForeign(['incident_uuid']);
            $table->dropIndex(['incident_uuid', 'occurred_at']);
            $table->dropColumn(['schema_version', 'incident_uuid']);
            $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('subject_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }
};
