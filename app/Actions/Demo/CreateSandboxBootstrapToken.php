<?php

namespace App\Actions\Demo;

use App\Models\SandboxBootstrapToken;
use App\Models\SandboxPair;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateSandboxBootstrapToken
{
    public function handle(SandboxPair $pair): string
    {
        return DB::transaction(function () use ($pair): string {
            $pair = SandboxPair::query()->lockForUpdate()->findOrFail($pair->id);

            if (! $pair->status->isAccessible() || $pair->expires_at->isPast()) {
                throw new \LogicException('Bootstrap tokens require an accessible, unexpired sandbox pair.');
            }

            $pair->bootstrapTokens()
                ->whereNull('used_at')
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            $plainTextToken = Str::random(64);

            SandboxBootstrapToken::query()->create([
                'sandbox_pair_id' => $pair->id,
                'token_hash' => hash('sha256', $plainTextToken),
                'generation' => $pair->generation,
                'expires_at' => $pair->expires_at,
            ]);

            return $plainTextToken;
        });
    }
}
