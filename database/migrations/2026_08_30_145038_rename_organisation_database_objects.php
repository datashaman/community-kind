<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->runForPostgreSql([
            'ALTER SEQUENCE teams_id_seq RENAME TO organisations_id_seq',
            'ALTER SEQUENCE team_members_id_seq RENAME TO organisation_members_id_seq',
            'ALTER SEQUENCE team_invitations_id_seq RENAME TO organisation_invitations_id_seq',
            'ALTER TABLE organisations RENAME CONSTRAINT teams_pkey TO organisations_pkey',
            'ALTER TABLE organisation_members RENAME CONSTRAINT team_members_pkey TO organisation_members_pkey',
            'ALTER TABLE organisation_invitations RENAME CONSTRAINT team_invitations_pkey TO organisation_invitations_pkey',
            'ALTER INDEX teams_slug_unique RENAME TO organisations_slug_unique',
            'ALTER INDEX team_members_team_id_user_id_unique RENAME TO organisation_members_organisation_id_user_id_unique',
            'ALTER INDEX team_invitations_token_hash_unique RENAME TO organisation_invitations_token_hash_unique',
            'ALTER INDEX programs_team_id_slug_unique RENAME TO programs_organisation_id_slug_unique',
            'ALTER TABLE users RENAME CONSTRAINT users_current_team_id_foreign TO users_current_organisation_id_foreign',
            'ALTER TABLE organisation_members RENAME CONSTRAINT team_members_team_id_foreign TO organisation_members_organisation_id_foreign',
            'ALTER TABLE organisation_members RENAME CONSTRAINT team_members_user_id_foreign TO organisation_members_user_id_foreign',
            'ALTER TABLE organisation_invitations RENAME CONSTRAINT team_invitations_team_id_foreign TO organisation_invitations_organisation_id_foreign',
            'ALTER TABLE organisation_invitations RENAME CONSTRAINT team_invitations_invited_by_foreign TO organisation_invitations_invited_by_foreign',
            'ALTER TABLE organisation_invitations RENAME CONSTRAINT team_invitations_revoked_by_foreign TO organisation_invitations_revoked_by_foreign',
            'ALTER TABLE programs RENAME CONSTRAINT programs_team_id_foreign TO programs_organisation_id_foreign',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->runForPostgreSql([
            'ALTER TABLE programs RENAME CONSTRAINT programs_organisation_id_foreign TO programs_team_id_foreign',
            'ALTER TABLE organisation_invitations RENAME CONSTRAINT organisation_invitations_revoked_by_foreign TO team_invitations_revoked_by_foreign',
            'ALTER TABLE organisation_invitations RENAME CONSTRAINT organisation_invitations_invited_by_foreign TO team_invitations_invited_by_foreign',
            'ALTER TABLE organisation_invitations RENAME CONSTRAINT organisation_invitations_organisation_id_foreign TO team_invitations_team_id_foreign',
            'ALTER TABLE organisation_members RENAME CONSTRAINT organisation_members_user_id_foreign TO team_members_user_id_foreign',
            'ALTER TABLE organisation_members RENAME CONSTRAINT organisation_members_organisation_id_foreign TO team_members_team_id_foreign',
            'ALTER TABLE users RENAME CONSTRAINT users_current_organisation_id_foreign TO users_current_team_id_foreign',
            'ALTER INDEX programs_organisation_id_slug_unique RENAME TO programs_team_id_slug_unique',
            'ALTER INDEX organisation_invitations_token_hash_unique RENAME TO team_invitations_token_hash_unique',
            'ALTER INDEX organisation_members_organisation_id_user_id_unique RENAME TO team_members_team_id_user_id_unique',
            'ALTER INDEX organisations_slug_unique RENAME TO teams_slug_unique',
            'ALTER TABLE organisation_invitations RENAME CONSTRAINT organisation_invitations_pkey TO team_invitations_pkey',
            'ALTER TABLE organisation_members RENAME CONSTRAINT organisation_members_pkey TO team_members_pkey',
            'ALTER TABLE organisations RENAME CONSTRAINT organisations_pkey TO teams_pkey',
            'ALTER SEQUENCE organisation_invitations_id_seq RENAME TO team_invitations_id_seq',
            'ALTER SEQUENCE organisation_members_id_seq RENAME TO team_members_id_seq',
            'ALTER SEQUENCE organisations_id_seq RENAME TO teams_id_seq',
        ]);
    }

    /**
     * Run PostgreSQL-specific schema metadata statements.
     *
     * @param  array<int, string>  $statements
     */
    private function runForPostgreSql(array $statements): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($statements as $statement) {
            DB::statement($statement);
        }
    }
};
