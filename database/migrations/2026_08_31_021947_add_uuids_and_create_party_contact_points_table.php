<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->uuid('uuid')->nullable();
        });

        DB::table('organisations')->orderBy('id')->eachById(function (object $organisation): void {
            DB::table('organisations')->where('id', $organisation->id)->update(['uuid' => (string) Str::uuid7()]);
        });

        Schema::table('organisations', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
            $table->unique('uuid');
        });

        Schema::table('parties', function (Blueprint $table) {
            $table->uuid('uuid')->nullable();
        });

        DB::table('parties')->orderBy('id')->eachById(function (object $party): void {
            DB::table('parties')->where('id', $party->id)->update(['uuid' => (string) Str::uuid7()]);
        });

        Schema::table('parties', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
            $table->unique('uuid');
        });

        Schema::create('party_contact_points', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('organisation_id');
            $table->foreignId('party_id');
            $table->string('type', 32);
            $table->text('encrypted_value');
            $table->string('data_key_version', 64);
            $table->string('current_index_key_version', 64);
            $table->char('current_blind_index', 64);
            $table->string('previous_index_key_version', 64)->nullable();
            $table->char('previous_blind_index', 64)->nullable();
            $table->timestamps();

            $table->foreign(['organisation_id', 'party_id'])
                ->references(['organisation_id', 'id'])->on('parties')->restrictOnDelete();
            $table->index(
                ['organisation_id', 'type', 'current_index_key_version', 'current_blind_index'],
                'party_contacts_current_lookup_index',
            );
            $table->index(
                ['organisation_id', 'type', 'previous_index_key_version', 'previous_blind_index'],
                'party_contacts_previous_lookup_index',
            );
        });

        $this->preventContactOwnershipChanges();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->allowContactOwnershipChanges();
        Schema::dropIfExists('party_contact_points');

        Schema::table('parties', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });

        Schema::table('organisations', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }

    private function preventContactOwnershipChanges(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION prevent_party_contact_organisation_change() RETURNS trigger AS $$
                BEGIN
                    IF NEW.organisation_id IS DISTINCT FROM OLD.organisation_id THEN
                        RAISE EXCEPTION 'Party contact Organisation ownership is immutable.';
                    END IF;

                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER party_contact_points_organisation_id_immutable
                    BEFORE UPDATE OF organisation_id ON party_contact_points
                    FOR EACH ROW
                    EXECUTE FUNCTION prevent_party_contact_organisation_change();
                SQL);

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER party_contact_points_organisation_id_immutable
                    BEFORE UPDATE OF organisation_id ON party_contact_points
                    FOR EACH ROW
                    WHEN NEW.organisation_id != OLD.organisation_id
                BEGIN
                    SELECT RAISE(ABORT, 'Party contact Organisation ownership is immutable.');
                END;
                SQL);
        }
    }

    private function allowContactOwnershipChanges(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS party_contact_points_organisation_id_immutable ON party_contact_points;
                DROP FUNCTION IF EXISTS prevent_party_contact_organisation_change();
                SQL);

            return;
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::unprepared('DROP TRIGGER IF EXISTS party_contact_points_organisation_id_immutable');
        }
    }
};
