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
        Schema::table('team_invitations', function (Blueprint $table) {
            $table->string('token_hash', 64)->nullable()->unique()->after('id');
            $table->timestamp('revoked_at')->nullable()->after('accepted_at');
            $table->foreignId('revoked_by')->nullable()->after('revoked_at')->constrained('users')->nullOnDelete();
        });

        DB::table('team_invitations')
            ->orderBy('id')
            ->eachById(function (object $invitation): void {
                DB::table('team_invitations')
                    ->where('id', $invitation->id)
                    ->update(['token_hash' => hash('sha256', $invitation->code)]);
            });

        Schema::table('team_invitations', function (Blueprint $table) {
            $table->string('token_hash', 64)->nullable(false)->change();
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('team_invitations', function (Blueprint $table) {
            $table->string('code', 64)->nullable()->unique()->after('id');
        });

        DB::table('team_invitations')
            ->orderBy('id')
            ->eachById(function (object $invitation): void {
                DB::table('team_invitations')
                    ->where('id', $invitation->id)
                    ->update(['code' => $invitation->token_hash]);
            });

        Schema::table('team_invitations', function (Blueprint $table) {
            $table->string('code', 64)->nullable(false)->change();
            $table->dropConstrainedForeignId('revoked_by');
            $table->dropColumn(['token_hash', 'revoked_at']);
        });
    }
};
