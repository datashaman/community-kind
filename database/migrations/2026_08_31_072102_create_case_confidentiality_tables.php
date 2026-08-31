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
        Schema::create('restricted_access_grants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id');
            $table->foreignId('program_id');
            $table->uuid('service_case_id')->nullable();
            $table->string('permission', 64);
            $table->string('reason', 255);
            $table->timestamp('granted_at');
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('granted_by_user_id')->nullable()->index();

            $table->foreign(['organisation_id', 'program_id'])
                ->references(['organisation_id', 'id'])->on('programs')->restrictOnDelete();
            $table->foreign(['organisation_id', 'service_case_id'])
                ->references(['organisation_id', 'id'])->on('service_cases')->restrictOnDelete();
            $table->unique(['organisation_id', 'id']);
            $table->index(['organisation_id', 'membership_id', 'program_id', 'permission'], 'restricted_grant_lookup');
        });

        Schema::create('restricted_access_revocations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->ulid('restricted_access_grant_id');
            $table->string('reason', 255);
            $table->timestamp('revoked_at');
            $table->unsignedBigInteger('revoked_by_user_id')->nullable()->index();

            $table->foreign(['organisation_id', 'restricted_access_grant_id'], 'restricted_revocation_grant_foreign')
                ->references(['organisation_id', 'id'])->on('restricted_access_grants')->restrictOnDelete();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'restricted_access_grant_id'], 'restricted_grant_single_revocation');
        });

        Schema::create('case_risk_assessments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->uuid('service_case_id');
            $table->string('type', 32)->default('risk_assessment');
            $table->string('classification', 32)->default('highly_restricted');
            $table->text('encrypted_content');
            $table->string('data_key_version', 64);
            $table->timestamp('effective_at');
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(['organisation_id', 'service_case_id'])
                ->references(['organisation_id', 'id'])->on('service_cases')->restrictOnDelete();
            $table->unique(['organisation_id', 'id']);
            $table->index(['organisation_id', 'service_case_id', 'ended_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            foreach (['restricted_access_grants', 'restricted_access_revocations'] as $table) {
                DB::unprepared("CREATE TRIGGER {$table}_append_only BEFORE UPDATE OR DELETE OR TRUNCATE ON {$table} FOR EACH STATEMENT EXECUTE FUNCTION prevent_append_only_mutation()");
            }
        } elseif (DB::getDriverName() === 'sqlite') {
            foreach (['restricted_access_grants', 'restricted_access_revocations'] as $table) {
                DB::unprepared("CREATE TRIGGER {$table}_update BEFORE UPDATE ON {$table} BEGIN SELECT RAISE(ABORT, 'append-only table'); END");
                DB::unprepared("CREATE TRIGGER {$table}_delete BEFORE DELETE ON {$table} BEGIN SELECT RAISE(ABORT, 'append-only table'); END");
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_risk_assessments');
        Schema::dropIfExists('restricted_access_revocations');
        Schema::dropIfExists('restricted_access_grants');
    }
};
