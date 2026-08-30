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
        Schema::rename('teams', 'organisations');
        Schema::rename('team_members', 'organisation_members');
        Schema::rename('team_invitations', 'organisation_invitations');

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('current_team_id', 'current_organisation_id');
        });

        Schema::table('organisation_members', function (Blueprint $table) {
            $table->renameColumn('team_id', 'organisation_id');
        });

        Schema::table('organisation_invitations', function (Blueprint $table) {
            $table->renameColumn('team_id', 'organisation_id');
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->renameColumn('team_id', 'organisation_id');
        });

        DB::table('organisation_members')
            ->where('role', 'team_administrator')
            ->update(['role' => 'organisation_administrator']);
        DB::table('organisation_invitations')
            ->where('role', 'team_administrator')
            ->update(['role' => 'organisation_administrator']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('organisation_members')
            ->where('role', 'organisation_administrator')
            ->update(['role' => 'team_administrator']);
        DB::table('organisation_invitations')
            ->where('role', 'organisation_administrator')
            ->update(['role' => 'team_administrator']);

        Schema::table('programs', function (Blueprint $table) {
            $table->renameColumn('organisation_id', 'team_id');
        });

        Schema::table('organisation_invitations', function (Blueprint $table) {
            $table->renameColumn('organisation_id', 'team_id');
        });

        Schema::table('organisation_members', function (Blueprint $table) {
            $table->renameColumn('organisation_id', 'team_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('current_organisation_id', 'current_team_id');
        });

        Schema::rename('organisation_invitations', 'team_invitations');
        Schema::rename('organisation_members', 'team_members');
        Schema::rename('organisations', 'teams');
    }
};
