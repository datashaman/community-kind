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
        Schema::table('supporter_journeys', function (Blueprint $table) {
            $table->string('journey_kind', 32)->default('general')->after('name');
            $table->string('channel', 24)->default('email')->after('journey_kind');
            $table->unsignedInteger('version')->default(1)->after('status');
            $table->timestamp('scheduled_for')->nullable()->after('approved_at');
            $table->timestamp('paused_at')->nullable()->after('scheduled_for');
            $table->json('experiment')->nullable()->after('paused_at');
        });
        Schema::table('supporter_journey_recipients', function (Blueprint $table) {
            $table->string('variant', 1)->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supporter_journeys', function (Blueprint $table) {
            $table->dropColumn(['journey_kind', 'channel', 'version', 'scheduled_for', 'paused_at', 'experiment']);
        });
        Schema::table('supporter_journey_recipients', function (Blueprint $table) {
            $table->dropColumn('variant');
        });
    }
};
