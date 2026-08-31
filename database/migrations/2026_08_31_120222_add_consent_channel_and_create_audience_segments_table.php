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
        Schema::table('party_consents', function (Blueprint $table) {
            $table->string('channel', 32)->default('not_applicable')->after('purpose');
            $table->index(['organisation_id', 'party_id', 'purpose', 'channel', 'occurred_at'], 'party_consents_audience_lookup');
        });

        Schema::create('audience_segments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->json('criteria');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audience_segments');

        Schema::table('party_consents', function (Blueprint $table) {
            $table->dropIndex('party_consents_audience_lookup');
            $table->dropColumn('channel');
        });
    }
};
