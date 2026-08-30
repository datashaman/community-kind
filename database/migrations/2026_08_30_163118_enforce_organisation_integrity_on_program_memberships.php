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
        Schema::table('programs', function (Blueprint $table) {
            $table->softDeletes();
            $table->unique(['organisation_id', 'id'], 'programs_organisation_id_id_unique');
        });

        Schema::table('organisation_members', function (Blueprint $table) {
            $table->unique(['organisation_id', 'id'], 'organisation_members_organisation_id_id_unique');
        });

        Schema::table('membership_program', function (Blueprint $table) {
            $table->foreignId('organisation_id')->nullable()->after('id');
        });

        DB::table('membership_program')->update([
            'organisation_id' => DB::table('programs')
                ->select('organisation_id')
                ->whereColumn('programs.id', 'membership_program.program_id')
                ->limit(1),
        ]);

        Schema::table('membership_program', function (Blueprint $table) {
            $table->foreignId('organisation_id')->nullable(false)->change();
            $table->foreign('organisation_id', 'membership_program_organisation_foreign')
                ->references('id')->on('organisations')->cascadeOnDelete();
            $table->foreign(['organisation_id', 'membership_id'], 'membership_program_membership_tenant_foreign')
                ->references(['organisation_id', 'id'])->on('organisation_members')->cascadeOnDelete();
            $table->foreign(['organisation_id', 'program_id'], 'membership_program_program_tenant_foreign')
                ->references(['organisation_id', 'id'])->on('programs')->cascadeOnDelete();
        });

        $this->preventProgramOwnershipChanges();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->allowProgramOwnershipChanges();

        Schema::table('membership_program', function (Blueprint $table) {
            $table->dropForeign('membership_program_membership_tenant_foreign');
            $table->dropForeign('membership_program_program_tenant_foreign');
            $table->dropForeign('membership_program_organisation_foreign');
            $table->dropColumn('organisation_id');
        });

        Schema::table('organisation_members', function (Blueprint $table) {
            $table->dropUnique('organisation_members_organisation_id_id_unique');
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->dropUnique('programs_organisation_id_id_unique');
            $table->dropSoftDeletes();
        });
    }

    private function preventProgramOwnershipChanges(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION prevent_program_organisation_change() RETURNS trigger AS $$
                BEGIN
                    IF NEW.organisation_id IS DISTINCT FROM OLD.organisation_id THEN
                        RAISE EXCEPTION 'Program Organisation ownership is immutable.';
                    END IF;

                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER programs_organisation_id_immutable
                    BEFORE UPDATE OF organisation_id ON programs
                    FOR EACH ROW
                    EXECUTE FUNCTION prevent_program_organisation_change();
                SQL);

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER programs_organisation_id_immutable
                    BEFORE UPDATE OF organisation_id ON programs
                    FOR EACH ROW
                    WHEN NEW.organisation_id != OLD.organisation_id
                BEGIN
                    SELECT RAISE(ABORT, 'Program Organisation ownership is immutable.');
                END;
                SQL);
        }
    }

    private function allowProgramOwnershipChanges(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS programs_organisation_id_immutable ON programs;
                DROP FUNCTION IF EXISTS prevent_program_organisation_change();
                SQL);

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS programs_organisation_id_immutable');
        }
    }
};
