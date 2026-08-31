<?php

namespace App\Actions\Demo;

use App\Enums\OrganisationStatus;
use App\Enums\SandboxPairStatus;
use App\Models\SandboxPair;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class PurgeSandboxPair
{
    public function handle(SandboxPair $pair): void
    {
        try {
            DB::transaction(function () use ($pair): void {
                $pair = SandboxPair::query()->lockForUpdate()->findOrFail($pair->id);

                if ($pair->status === SandboxPairStatus::Purged) {
                    return;
                }

                if (! in_array($pair->status, [SandboxPairStatus::Expired, SandboxPairStatus::Failed, SandboxPairStatus::Purging], true)) {
                    throw new \LogicException('Only expired or failed sandboxes can be purged.');
                }

                $pair->update(['status' => SandboxPairStatus::Purging]);
                $organisations = $pair->organisations()->withTrashed()->get();
                $organisationIds = $organisations->pluck('id');
                $userIds = DB::table('organisation_members')->whereIn('organisation_id', $organisationIds)->pluck('user_id');

                DB::table('sessions')->whereIn('user_id', $userIds)->delete();
                $pair->bootstrapTokens()->delete();

                foreach ($organisations as $organisation) {
                    $organisation->forceFill([
                        'sandbox_pair_id' => null,
                        'status' => OrganisationStatus::Deleted,
                        'status_changed_at' => now(),
                        'slug' => 'purged-sandbox-'.$organisation->id.'-'.Str::lower((string) Str::ulid()),
                        'name' => 'Purged synthetic sandbox',
                    ])->save();
                    $organisation->delete();
                }

                User::query()->whereIn('id', $userIds)->each(function (User $user) use ($organisationIds): void {
                    $hasExternalMembership = DB::table('organisation_members')
                        ->where('user_id', $user->id)
                        ->whereNotIn('organisation_id', $organisationIds)
                        ->whereNull('ended_at')
                        ->exists();

                    if (! $hasExternalMembership) {
                        $user->forceFill([
                            'name' => 'Purged synthetic sandbox user',
                            'email' => 'purged-sandbox-'.$user->id.'-'.Str::lower((string) Str::ulid()).'@example.test',
                            'email_verified_at' => null,
                            'password' => Str::random(64),
                            'current_organisation_id' => null,
                            'two_factor_secret' => null,
                            'two_factor_recovery_codes' => null,
                            'two_factor_confirmed_at' => null,
                            'recovery_codes_acknowledged_at' => null,
                            'remember_token' => null,
                        ])->save();
                    }
                });

                $pair->update(['status' => SandboxPairStatus::Purged, 'purged_at' => now()]);
            });
        } catch (Throwable $exception) {
            SandboxPair::query()
                ->whereKey($pair->id)
                ->whereIn('status', [SandboxPairStatus::Expired, SandboxPairStatus::Failed, SandboxPairStatus::Purging])
                ->update(['status' => SandboxPairStatus::Failed, 'failed_at' => now()]);

            throw $exception;
        }
    }
}
