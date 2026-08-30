<?php

namespace App\Actions\Organisations;

use App\Models\Organisation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class InvalidateOrganisationAccess
{
    public function handle(Organisation $organisation): void
    {
        Organisation::whereKey($organisation->id)->update([
            'access_version' => DB::raw('access_version + 1'),
            'signed_links_invalidated_at' => now(),
        ]);
        $organisation->refresh();

        Cache::forget("organisation:{$organisation->id}:access");
    }
}
