<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('portal_access_grants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('person_party_id');
            $table->char('token_hash', 64)->unique();
            $table->unsignedInteger('access_version')->default(1);
            $table->timestamp('token_expires_at')->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('token_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organisation_id', 'id']);
            $table->foreign(['organisation_id', 'person_party_id'])
                ->references(['organisation_id', 'id'])->on('parties')->restrictOnDelete();
            $table->index(['organisation_id', 'user_id', 'revoked_at']);
            $table->index(['organisation_id', 'person_party_id', 'revoked_at']);
        });

        DB::statement('CREATE UNIQUE INDEX portal_access_grants_active_user_unique ON portal_access_grants (organisation_id, user_id) WHERE revoked_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX portal_access_grants_active_party_unique ON portal_access_grants (organisation_id, person_party_id) WHERE revoked_at IS NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portal_access_grants');
    }
};
