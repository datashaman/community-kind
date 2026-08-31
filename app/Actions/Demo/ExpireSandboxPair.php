<?php

namespace App\Actions\Demo;

use App\Enums\OrganisationStatus;
use App\Enums\SandboxPairStatus;
use App\Models\SandboxPair;
use Illuminate\Support\Facades\DB;

final class ExpireSandboxPair
{
    public function handle(SandboxPair $pair): void
    {
        DB::transaction(function () use ($pair): void {
            $pair = SandboxPair::query()->lockForUpdate()->findOrFail($pair->id);

            if (in_array($pair->status, [SandboxPairStatus::Expired, SandboxPairStatus::Purging, SandboxPairStatus::Purged], true)) {
                return;
            }

            if (! in_array($pair->status, [SandboxPairStatus::Ready, SandboxPairStatus::Active], true)) {
                throw new \LogicException('Only ready or active sandboxes can expire.');
            }

            $userIds = DB::table('organisation_members')
                ->whereIn('organisation_id', $pair->organisations()->select('id'))
                ->pluck('user_id');

            DB::table('sessions')->whereIn('user_id', $userIds)->delete();
            $pair->bootstrapTokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $pair->organisations()->update([
                'status' => OrganisationStatus::Archived,
                'status_changed_at' => now(),
                'access_version' => DB::raw('access_version + 1'),
                'signed_links_invalidated_at' => now(),
            ]);
            $pair->update([
                'status' => SandboxPairStatus::Expired,
                'generation' => $pair->generation + 1,
            ]);
        });
    }
}
