<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_cases', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable();
            $table->string('closure_reason', 64)->nullable();
            $table->timestamp('follow_up_at')->nullable();
            $table->json('closure_checklist')->nullable();
        });

        Schema::create('case_goals', function (Blueprint $table) {
            $this->caseRecord($table);
            $table->string('status', 32)->default('draft');
            $table->timestamp('target_at')->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->string('terminal_reason', 64)->nullable();
            $this->caseRecordIndexes($table, 'case_goals');
        });

        Schema::create('case_services', function (Blueprint $table) {
            $this->caseRecord($table);
            $table->string('service_code', 64);
            $table->string('status', 32)->default('planned');
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('terminal_reason', 64)->nullable();
            $this->caseRecordIndexes($table, 'case_services');
        });

        Schema::create('external_referrals', function (Blueprint $table) {
            $this->caseRecord($table);
            $table->string('status', 32)->default('draft');
            $table->string('sharing_authority', 64);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->string('terminal_reason', 64)->nullable();
            $table->timestamp('carried_forward_at')->nullable();
            $table->string('carry_forward_reason', 64)->nullable();
            $this->caseRecordIndexes($table, 'external_referrals');
        });

        Schema::create('case_tasks', function (Blueprint $table) {
            $this->caseRecord($table);
            $table->string('status', 32)->default('open');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->string('terminal_reason', 64)->nullable();
            $this->caseRecordIndexes($table, 'case_tasks');
        });

        Schema::create('case_appointments', function (Blueprint $table) {
            $this->caseRecord($table);
            $table->string('status', 32)->default('scheduled');
            $table->timestamp('scheduled_at');
            $table->timestamp('effective_at')->nullable();
            $table->string('terminal_reason', 64)->nullable();
            $table->uuid('completed_service_id')->nullable();
            $this->caseRecordIndexes($table, 'case_appointments');
            $table->foreign(['organisation_id', 'completed_service_id'])
                ->references(['organisation_id', 'id'])->on('case_services')->restrictOnDelete();
        });

        Schema::create('case_interactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->uuid('service_case_id');
            $table->string('interaction_type', 64);
            $table->string('type', 32)->default('content');
            $table->text('encrypted_content');
            $table->string('data_key_version', 64);
            $table->timestamp('occurred_at');
            $table->timestamp('recorded_at');
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['organisation_id', 'id']);
            $table->foreign(['organisation_id', 'service_case_id'])
                ->references(['organisation_id', 'id'])->on('service_cases')->cascadeOnDelete();
            $table->index(['organisation_id', 'service_case_id', 'occurred_at']);
        });

        Schema::create('case_notes', function (Blueprint $table) {
            $this->caseRecord($table);
            $table->string('status', 32)->default('draft');
            $table->uuid('corrects_note_id')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('authored_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $this->caseRecordIndexes($table, 'case_notes');
            $table->foreign(['organisation_id', 'corrects_note_id'])
                ->references(['organisation_id', 'id'])->on('case_notes')->restrictOnDelete();
        });

        Schema::create('case_outcomes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->uuid('service_case_id');
            $table->json('measures');
            $table->string('type', 32)->default('content');
            $table->text('encrypted_content');
            $table->string('data_key_version', 64);
            $table->timestamp('effective_at');
            $table->timestamp('recorded_at');
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['organisation_id', 'id']);
            $table->unique(['organisation_id', 'service_case_id']);
            $table->foreign(['organisation_id', 'service_case_id'])
                ->references(['organisation_id', 'id'])->on('service_cases')->cascadeOnDelete();
        });

        Schema::create('case_workflow_transitions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->uuid('service_case_id');
            $table->string('subject_type', 32);
            $table->uuid('subject_id');
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('reason', 64)->nullable();
            $table->timestamp('effective_at');
            $table->timestamp('recorded_at');
            $table->unsignedInteger('version');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreign(['organisation_id', 'service_case_id'])
                ->references(['organisation_id', 'id'])->on('service_cases')->cascadeOnDelete();
            $table->unique(['organisation_id', 'subject_type', 'subject_id', 'version'], 'case_workflow_transition_version_unique');
            $table->index(['organisation_id', 'service_case_id', 'recorded_at']);
        });

        Schema::create('workflow_corrections', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->uuid('service_case_id');
            $table->string('subject_type', 32);
            $table->uuid('subject_id');
            $table->string('correction_type', 32);
            $table->string('reason', 64);
            $table->json('replacement_values')->nullable();
            $table->timestamp('effective_at');
            $table->timestamp('recorded_at');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreign(['organisation_id', 'service_case_id'])
                ->references(['organisation_id', 'id'])->on('service_cases')->cascadeOnDelete();
            $table->index(['organisation_id', 'subject_type', 'subject_id']);
        });

        Schema::create('metric_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id');
            $table->string('code', 64);
            $table->decimal('value', 14, 4)->default(1);
            $table->json('dimensions')->default('{}');
            $table->string('deduplication_key', 64);
            $table->timestamp('occurred_at');
            $table->timestamp('recorded_at');
            $table->foreign(['organisation_id', 'program_id'])
                ->references(['organisation_id', 'id'])->on('programs')->restrictOnDelete();
            $table->unique(['organisation_id', 'deduplication_key']);
            $table->index(['organisation_id', 'program_id', 'code', 'occurred_at']);
        });

        $this->guardAppendOnlyTables();
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS guard_case_delivery_history() CASCADE');
        }

        Schema::dropIfExists('metric_events');
        Schema::dropIfExists('workflow_corrections');
        Schema::dropIfExists('case_workflow_transitions');
        Schema::dropIfExists('case_outcomes');
        Schema::dropIfExists('case_notes');
        Schema::dropIfExists('case_interactions');
        Schema::dropIfExists('case_appointments');
        Schema::dropIfExists('case_tasks');
        Schema::dropIfExists('external_referrals');
        Schema::dropIfExists('case_services');
        Schema::dropIfExists('case_goals');

        Schema::table('service_cases', function (Blueprint $table) {
            $table->dropColumn(['closed_at', 'closure_reason', 'follow_up_at', 'closure_checklist']);
        });
    }

    private function caseRecord(Blueprint $table): void
    {
        $table->uuid('id')->primary();
        $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
        $table->uuid('service_case_id');
        $table->string('type', 32)->default('content');
        $table->text('encrypted_content');
        $table->string('data_key_version', 64);
        $table->unsignedInteger('version')->default(1);
        $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        $table->timestamps();
    }

    private function caseRecordIndexes(Blueprint $table, string $tableName): void
    {
        $table->unique(['organisation_id', 'id']);
        $table->foreign(['organisation_id', 'service_case_id'], "{$tableName}_organisation_case_foreign")
            ->references(['organisation_id', 'id'])->on('service_cases')->cascadeOnDelete();
        $table->index(['organisation_id', 'service_case_id', 'status']);
    }

    private function guardAppendOnlyTables(): void
    {
        $tables = ['case_workflow_transitions', 'workflow_corrections', 'metric_events', 'case_interactions', 'case_outcomes'];

        if (DB::getDriverName() === 'sqlite') {
            foreach ($tables as $table) {
                DB::unprepared("CREATE TRIGGER {$table}_append_only_update BEFORE UPDATE ON {$table} BEGIN SELECT RAISE(ABORT, 'Case delivery history is append-only.'); END");
                DB::unprepared("CREATE TRIGGER {$table}_append_only_delete BEFORE DELETE ON {$table} BEGIN SELECT RAISE(ABORT, 'Case delivery history is append-only.'); END");
            }

            return;
        }

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION guard_case_delivery_history() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'Case delivery history is append-only.';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER case_workflow_transitions_append_only BEFORE UPDATE OR DELETE ON case_workflow_transitions
                FOR EACH ROW EXECUTE FUNCTION guard_case_delivery_history();
            CREATE TRIGGER workflow_corrections_append_only BEFORE UPDATE OR DELETE ON workflow_corrections
                FOR EACH ROW EXECUTE FUNCTION guard_case_delivery_history();
            CREATE TRIGGER metric_events_append_only BEFORE UPDATE OR DELETE ON metric_events
                FOR EACH ROW EXECUTE FUNCTION guard_case_delivery_history();
            CREATE TRIGGER case_interactions_append_only BEFORE UPDATE OR DELETE ON case_interactions
                FOR EACH ROW EXECUTE FUNCTION guard_case_delivery_history();
            CREATE TRIGGER case_outcomes_append_only BEFORE UPDATE OR DELETE ON case_outcomes
                FOR EACH ROW EXECUTE FUNCTION guard_case_delivery_history();
            SQL);
    }
};
