<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Models\PublishedImpactSnapshot;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ImpactController extends Controller
{
    public function __invoke(Request $request): View
    {
        $organisation = $request->attributes->get('public_organisation');
        abort_unless($organisation instanceof Organisation, 404);
        $snapshot = PublishedImpactSnapshot::query()->where('audience', 'public')->whereNotNull('published_at')->latest('published_at')->firstOrFail();

        return view('public.impact', compact('organisation', 'snapshot'));
    }
}
