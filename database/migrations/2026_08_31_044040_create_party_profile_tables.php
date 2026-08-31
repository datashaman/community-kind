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
        Schema::create('party_program', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id');
            $table->foreignId('program_id');
            $table->timestamps();

            $table->foreign(['organisation_id', 'party_id'])
                ->references(['organisation_id', 'id'])->on('parties')->cascadeOnDelete();
            $table->foreign(['organisation_id', 'program_id'])
                ->references(['organisation_id', 'id'])->on('programs')->cascadeOnDelete();
            $table->unique(['organisation_id', 'party_id', 'program_id']);
        });

        Schema::create('party_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id');
            $table->string('role', 64);
            $table->timestamps();

            $table->foreign(['organisation_id', 'party_id'])
                ->references(['organisation_id', 'id'])->on('parties')->cascadeOnDelete();
            $table->unique(['organisation_id', 'party_id', 'role']);
        });

        Schema::create('party_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id');
            $table->foreignId('related_party_id');
            $table->string('type', 64);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->foreign(['organisation_id', 'party_id'], 'party_relationships_party_foreign')
                ->references(['organisation_id', 'id'])->on('parties')->cascadeOnDelete();
            $table->foreign(['organisation_id', 'related_party_id'], 'party_relationships_related_party_foreign')
                ->references(['organisation_id', 'id'])->on('parties')->restrictOnDelete();
            $table->index(['organisation_id', 'party_id', 'ended_at']);
        });

        Schema::create('party_addresses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id');
            $table->string('type', 32)->default('address');
            $table->string('label', 100);
            $table->text('encrypted_value');
            $table->string('data_key_version', 64);
            $table->string('service_area', 100)->nullable();
            $table->char('country_code', 2);
            $table->timestamps();

            $table->foreign(['organisation_id', 'party_id'])
                ->references(['organisation_id', 'id'])->on('parties')->cascadeOnDelete();
            $table->index(['organisation_id', 'service_area']);
        });

        Schema::create('party_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id');
            $table->string('slug', 100);
            $table->string('label', 100);
            $table->timestamps();

            $table->foreign(['organisation_id', 'party_id'])
                ->references(['organisation_id', 'id'])->on('parties')->cascadeOnDelete();
            $table->unique(['organisation_id', 'party_id', 'slug']);
        });

        Schema::create('party_consents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id');
            $table->string('purpose', 32);
            $table->string('decision', 32);
            $table->string('wording_version', 64);
            $table->text('wording');
            $table->string('source', 100);
            $table->timestamp('occurred_at');
            $table->ulid('supersedes_id')->nullable();
            $table->unsignedBigInteger('recorded_by_user_id')->nullable()->index();

            $table->foreign(['organisation_id', 'party_id'])
                ->references(['organisation_id', 'id'])->on('parties')->cascadeOnDelete();
            $table->unique(['organisation_id', 'id']);
            $table->index(['organisation_id', 'party_id', 'purpose', 'occurred_at']);
        });

        Schema::table('party_consents', function (Blueprint $table) {
            $table->foreign(['organisation_id', 'supersedes_id'])
                ->references(['organisation_id', 'id'])->on('party_consents')->restrictOnDelete();
        });

        Schema::create('party_safe_contact_instructions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id');
            $table->string('type', 32)->default('instruction');
            $table->text('encrypted_value');
            $table->string('data_key_version', 64);
            $table->string('source', 100);
            $table->timestamp('effective_at');
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(['organisation_id', 'party_id'])
                ->references(['organisation_id', 'id'])->on('parties')->cascadeOnDelete();
            $table->index(['organisation_id', 'party_id', 'ended_at']);
        });

        Schema::create('party_timeline_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('party_id');
            $table->string('type', 64);
            $table->string('subject_type', 64)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->string('summary', 255);
            $table->json('metadata')->default('{}');
            $table->timestamp('occurred_at');
            $table->unsignedBigInteger('recorded_by_user_id')->nullable()->index();

            $table->foreign(['organisation_id', 'party_id'])
                ->references(['organisation_id', 'id'])->on('parties')->cascadeOnDelete();
            $table->index(['organisation_id', 'party_id', 'occurred_at']);
        });

        foreach (['party_consents', 'party_timeline_events'] as $table) {
            if (DB::getDriverName() === 'pgsql') {
                DB::unprepared("CREATE TRIGGER {$table}_append_only BEFORE UPDATE OR DELETE OR TRUNCATE ON {$table} FOR EACH STATEMENT EXECUTE FUNCTION prevent_append_only_mutation() ");
            } elseif (DB::getDriverName() === 'sqlite') {
                DB::unprepared("CREATE TRIGGER {$table}_append_only_update BEFORE UPDATE ON {$table} BEGIN SELECT RAISE(ABORT, 'append-only table'); END");
                DB::unprepared("CREATE TRIGGER {$table}_append_only_delete BEFORE DELETE ON {$table} BEGIN SELECT RAISE(ABORT, 'append-only table'); END");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('party_timeline_events');
        Schema::dropIfExists('party_safe_contact_instructions');
        Schema::dropIfExists('party_consents');
        Schema::dropIfExists('party_interests');
        Schema::dropIfExists('party_addresses');
        Schema::dropIfExists('party_relationships');
        Schema::dropIfExists('party_roles');
        Schema::dropIfExists('party_program');
    }
};
