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
        Schema::create('case_assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->uuid('service_case_id');
            $table->foreignId('membership_id');
            $table->string('role', 32)->default('primary');
            $table->string('status', 32)->default('active');
            $table->boolean('active_primary_marker')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('assigned_reason', 64)->nullable();
            $table->string('ended_reason', 64)->nullable();
            $table->unsignedBigInteger('assigned_by_user_id')->nullable()->index();
            $table->unsignedBigInteger('ended_by_user_id')->nullable()->index();

            $table->foreign(['organisation_id', 'service_case_id'])
                ->references(['organisation_id', 'id'])->on('service_cases')->cascadeOnDelete();
            $table->foreign(['organisation_id', 'membership_id'])
                ->references(['organisation_id', 'id'])->on('organisation_members')->restrictOnDelete();
            $table->unique(['organisation_id', 'service_case_id', 'active_primary_marker']);
            $table->index(['organisation_id', 'membership_id', 'status']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION guard_case_assignment_history() RETURNS trigger AS $$
                BEGIN
                    IF TG_OP = 'DELETE' THEN
                        RAISE EXCEPTION 'Case assignment history is append-only.';
                    END IF;

                    IF OLD.status <> 'active'
                        OR NEW.status <> 'ended'
                        OR NEW.active_primary_marker IS NOT NULL
                        OR OLD.ended_at IS NOT NULL
                        OR NEW.ended_at IS NULL
                        OR NEW.id IS DISTINCT FROM OLD.id
                        OR NEW.organisation_id IS DISTINCT FROM OLD.organisation_id
                        OR NEW.service_case_id IS DISTINCT FROM OLD.service_case_id
                        OR NEW.membership_id IS DISTINCT FROM OLD.membership_id
                        OR NEW.role IS DISTINCT FROM OLD.role
                        OR NEW.started_at IS DISTINCT FROM OLD.started_at
                        OR NEW.assigned_reason IS DISTINCT FROM OLD.assigned_reason
                        OR NEW.assigned_by_user_id IS DISTINCT FROM OLD.assigned_by_user_id
                    THEN
                        RAISE EXCEPTION 'Case assignment history may only be ended.';
                    END IF;

                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER case_assignments_history_guard
                BEFORE UPDATE OR DELETE ON case_assignments
                FOR EACH ROW EXECUTE FUNCTION guard_case_assignment_history();
                SQL);
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER case_assignments_append_only_delete BEFORE DELETE ON case_assignments BEGIN SELECT RAISE(ABORT, 'Case assignment history is append-only.'); END");
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER case_assignments_history_guard
                BEFORE UPDATE ON case_assignments
                FOR EACH ROW
                WHEN OLD.status <> 'active'
                    OR NEW.status <> 'ended'
                    OR NEW.active_primary_marker IS NOT NULL
                    OR OLD.ended_at IS NOT NULL
                    OR NEW.ended_at IS NULL
                    OR NEW.id IS NOT OLD.id
                    OR NEW.organisation_id IS NOT OLD.organisation_id
                    OR NEW.service_case_id IS NOT OLD.service_case_id
                    OR NEW.membership_id IS NOT OLD.membership_id
                    OR NEW.role IS NOT OLD.role
                    OR NEW.started_at IS NOT OLD.started_at
                    OR NEW.assigned_reason IS NOT OLD.assigned_reason
                    OR NEW.assigned_by_user_id IS NOT OLD.assigned_by_user_id
                BEGIN
                    SELECT RAISE(ABORT, 'Case assignment history may only be ended.');
                END
                SQL);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS guard_case_assignment_history() CASCADE');
        }

        Schema::dropIfExists('case_assignments');
    }
};
