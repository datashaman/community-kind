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
        Schema::create('parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('display_name');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['organisation_id', 'id'], 'parties_organisation_id_id_unique');
            $table->index(['organisation_id', 'kind', 'display_name']);
        });

        Schema::table('organisation_members', function (Blueprint $table) {
            $table->foreignId('person_party_id')->nullable()->after('user_id');
            $table->timestamp('accepted_at')->nullable()->after('is_owner');
            $table->timestamp('ended_at')->nullable()->after('accepted_at');
        });

        DB::table('organisation_members')->update([
            'accepted_at' => DB::raw('created_at'),
        ]);

        DB::table('organisation_members')
            ->join('users', 'users.id', '=', 'organisation_members.user_id')
            ->whereNull('organisation_members.person_party_id')
            ->orderBy('organisation_members.id')
            ->select([
                'organisation_members.id as membership_id',
                'organisation_members.organisation_id',
                'organisation_members.created_at',
                'organisation_members.updated_at',
                'users.name',
            ])
            ->each(function (object $membership): void {
                $partyId = DB::table('parties')->insertGetId([
                    'organisation_id' => $membership->organisation_id,
                    'kind' => 'person',
                    'display_name' => $membership->name,
                    'created_at' => $membership->created_at ?? now(),
                    'updated_at' => $membership->updated_at ?? now(),
                ]);

                DB::table('organisation_members')
                    ->where('id', $membership->membership_id)
                    ->update(['person_party_id' => $partyId]);
            });

        Schema::table('organisation_members', function (Blueprint $table) {
            $table->foreignId('person_party_id')->nullable(false)->change();
            $table->timestamp('accepted_at')->nullable(false)->change();
            $table->foreign(['organisation_id', 'person_party_id'], 'organisation_members_person_party_tenant_foreign')
                ->references(['organisation_id', 'id'])->on('parties')->restrictOnDelete();
            $table->index(['organisation_id', 'user_id', 'ended_at'], 'organisation_members_active_lookup');
        });

        Schema::table('organisation_members', function (Blueprint $table) {
            $table->dropUnique(DB::getDriverName() === 'sqlite'
                ? 'team_members_team_id_user_id_unique'
                : 'organisation_members_organisation_id_user_id_unique');
        });

        $this->createActiveMembershipUniqueIndex();

        Schema::create('role_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id');
            $table->string('role');
            $table->foreignId('program_id')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->foreign(['organisation_id', 'membership_id'], 'role_assignments_membership_tenant_foreign')
                ->references(['organisation_id', 'id'])->on('organisation_members')->cascadeOnDelete();
            $table->foreign(['organisation_id', 'program_id'], 'role_assignments_program_tenant_foreign')
                ->references(['organisation_id', 'id'])->on('programs')->restrictOnDelete();
            $table->index(['organisation_id', 'membership_id', 'ended_at'], 'role_assignments_active_lookup');
        });

        DB::statement('CREATE UNIQUE INDEX role_assignments_one_active_organisation_scope ON role_assignments (organisation_id, membership_id, role) WHERE program_id IS NULL AND ended_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX role_assignments_one_active_program_scope ON role_assignments (organisation_id, membership_id, role, program_id) WHERE program_id IS NOT NULL AND ended_at IS NULL');

        DB::table('organisation_members')
            ->whereNotNull('role')
            ->orderBy('id')
            ->each(function (object $membership): void {
                $programIds = DB::table('membership_program')
                    ->where('membership_id', $membership->id)
                    ->pluck('program_id');

                if ($programIds->isEmpty()) {
                    $programIds = collect([null]);
                }

                foreach ($programIds as $programId) {
                    DB::table('role_assignments')->insert([
                        'organisation_id' => $membership->organisation_id,
                        'membership_id' => $membership->id,
                        'role' => $membership->role,
                        'program_id' => $programId,
                        'created_at' => $membership->created_at ?? now(),
                        'updated_at' => $membership->updated_at ?? now(),
                    ]);
                }
            });

        Schema::table('organisation_invitations', function (Blueprint $table) {
            $table->foreignId('person_party_id')->nullable()->after('email');
            $table->string('new_person_name')->nullable()->after('person_party_id');
            $table->boolean('offers_ownership')->default(false)->after('role');
            $table->unique(['organisation_id', 'id'], 'organisation_invitations_organisation_id_id_unique');
            $table->foreign(['organisation_id', 'person_party_id'], 'organisation_invitations_person_party_tenant_foreign')
                ->references(['organisation_id', 'id'])->on('parties')->restrictOnDelete();
        });

        DB::table('organisation_invitations')
            ->whereNull('person_party_id')
            ->update(['new_person_name' => DB::raw('email')]);

        Schema::create('organisation_invitation_role_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organisation_invitation_id');
            $table->string('role');
            $table->foreignId('program_id')->nullable();
            $table->timestamps();

            $table->foreign(['organisation_id', 'organisation_invitation_id'], 'invitation_roles_invitation_tenant_foreign')
                ->references(['organisation_id', 'id'])->on('organisation_invitations')->cascadeOnDelete();
            $table->foreign(['organisation_id', 'program_id'], 'invitation_roles_program_tenant_foreign')
                ->references(['organisation_id', 'id'])->on('programs')->restrictOnDelete();
        });

        DB::statement('CREATE UNIQUE INDEX invitation_roles_one_organisation_scope ON organisation_invitation_role_assignments (organisation_id, organisation_invitation_id, role) WHERE program_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX invitation_roles_one_program_scope ON organisation_invitation_role_assignments (organisation_id, organisation_invitation_id, role, program_id) WHERE program_id IS NOT NULL');

        DB::table('organisation_invitations')
            ->orderBy('id')
            ->each(function (object $invitation): void {
                DB::table('organisation_invitation_role_assignments')->insert([
                    'organisation_id' => $invitation->organisation_id,
                    'organisation_invitation_id' => $invitation->id,
                    'role' => $invitation->role,
                    'program_id' => null,
                    'created_at' => $invitation->created_at ?? now(),
                    'updated_at' => $invitation->updated_at ?? now(),
                ]);
            });

        Schema::create('membership_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('membership_id');
            $table->text('reason');
            $table->timestamp('starts_at');
            $table->timestamp('review_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign(['organisation_id', 'membership_id'], 'membership_holds_membership_tenant_foreign')
                ->references(['organisation_id', 'id'])->on('organisation_members')->cascadeOnDelete();
            $table->index(['organisation_id', 'membership_id', 'released_at'], 'membership_holds_active_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('membership_holds');
        Schema::dropIfExists('organisation_invitation_role_assignments');

        Schema::table('organisation_invitations', function (Blueprint $table) {
            $table->dropForeign(DB::getDriverName() === 'sqlite'
                ? ['organisation_id', 'person_party_id']
                : 'organisation_invitations_person_party_tenant_foreign');
            $table->dropUnique('organisation_invitations_organisation_id_id_unique');
            $table->dropColumn(['person_party_id', 'new_person_name', 'offers_ownership']);
        });

        Schema::dropIfExists('role_assignments');
        $this->dropActiveMembershipUniqueIndex();

        DB::statement(<<<'SQL'
            DELETE FROM organisation_members
            WHERE id NOT IN (
                SELECT id
                FROM (
                    SELECT id,
                        ROW_NUMBER() OVER (
                            PARTITION BY organisation_id, user_id
                            ORDER BY CASE WHEN ended_at IS NULL THEN 0 ELSE 1 END,
                                accepted_at DESC,
                                id DESC
                        ) AS tenure_rank
                    FROM organisation_members
                ) AS ranked_memberships
                WHERE tenure_rank = 1
            )
            SQL);

        Schema::table('organisation_members', function (Blueprint $table) {
            $table->dropForeign(DB::getDriverName() === 'sqlite'
                ? ['organisation_id', 'person_party_id']
                : 'organisation_members_person_party_tenant_foreign');
            $table->dropIndex('organisation_members_active_lookup');
            $table->dropColumn(['person_party_id', 'accepted_at', 'ended_at']);
            $table->unique(['organisation_id', 'user_id'], 'organisation_members_organisation_id_user_id_unique');
        });

        Schema::dropIfExists('parties');
    }

    private function createActiveMembershipUniqueIndex(): void
    {
        DB::statement('CREATE UNIQUE INDEX organisation_members_one_active_tenure ON organisation_members (organisation_id, user_id) WHERE ended_at IS NULL');
    }

    private function dropActiveMembershipUniqueIndex(): void
    {
        DB::statement('DROP INDEX IF EXISTS organisation_members_one_active_tenure');
    }
};
