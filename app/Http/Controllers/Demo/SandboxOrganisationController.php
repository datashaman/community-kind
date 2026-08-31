<?php

namespace App\Http\Controllers\Demo;

use App\Actions\Demo\ResetSandboxOrganisation;
use App\Http\Controllers\Controller;
use App\Models\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SandboxOrganisationController extends Controller
{
    public function reset(Request $request, Organisation $organisation, ResetSandboxOrganisation $reset): RedirectResponse
    {
        $request->validate(['slug' => ['required', 'string', 'in:'.$organisation->slug]]);
        $user = $request->user();
        abort_if($user === null, 403);
        $result = $reset->handle($organisation, $user);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('demo.bootstrap', ['token' => $result['token']]);
    }
}
