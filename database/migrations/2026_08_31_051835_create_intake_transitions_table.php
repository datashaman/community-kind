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
        Schema::create('intake_transitions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->uuid('intake_request_id');
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('reason', 64)->nullable();
            $table->timestamp('effective_at');
            $table->timestamp('recorded_at');
            $table->unsignedInteger('version');
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();

            $table->foreign(['organisation_id', 'intake_request_id'])
                ->references(['organisation_id', 'id'])->on('intake_requests')->cascadeOnDelete();
            $table->unique(['organisation_id', 'intake_request_id', 'version']);
            $table->index(['organisation_id', 'intake_request_id', 'effective_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('CREATE TRIGGER intake_transitions_append_only BEFORE UPDATE OR DELETE OR TRUNCATE ON intake_transitions FOR EACH STATEMENT EXECUTE FUNCTION prevent_append_only_mutation()');
        } elseif (DB::getDriverName() === 'sqlite') {
            DB::unprepared("CREATE TRIGGER intake_transitions_append_only_update BEFORE UPDATE ON intake_transitions BEGIN SELECT RAISE(ABORT, 'append-only table'); END");
            DB::unprepared("CREATE TRIGGER intake_transitions_append_only_delete BEFORE DELETE ON intake_transitions BEGIN SELECT RAISE(ABORT, 'append-only table'); END");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('intake_transitions');
    }
};
