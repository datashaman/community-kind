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
        Schema::table('teams', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('is_personal');
        });

        Schema::table('team_members', function (Blueprint $table) {
            $table->boolean('is_owner')->default(false)->after('user_id');
            $table->string('role')->nullable()->change();
        });

        DB::table('team_members')
            ->where('role', 'owner')
            ->update(['is_owner' => true, 'role' => null]);

        DB::table('team_members')->where('role', 'admin')->update(['role' => 'team_administrator']);
        DB::table('team_members')->where('role', 'member')->update(['role' => 'case_worker']);
        DB::table('team_invitations')->where('role', 'admin')->update(['role' => 'team_administrator']);
        DB::table('team_invitations')->where('role', 'member')->update(['role' => 'case_worker']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        DB::table('team_members')->where('role', 'team_administrator')->update(['role' => 'admin']);
        DB::table('team_members')->where('role', 'case_worker')->update(['role' => 'member']);
        DB::table('team_members')->where('is_owner', true)->update(['role' => 'owner']);
        DB::table('team_invitations')->where('role', 'team_administrator')->update(['role' => 'admin']);
        DB::table('team_invitations')->where('role', 'case_worker')->update(['role' => 'member']);

        Schema::table('team_members', function (Blueprint $table) {
            $table->string('role')->nullable(false)->change();
            $table->dropColumn('is_owner');
        });
    }
};
