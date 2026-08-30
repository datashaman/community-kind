<?php

namespace App\Actions\Fortify;

use Closure;
use Illuminate\Http\Request;

class DiscardRememberedLogin
{
    public function handle(Request $request, Closure $next): mixed
    {
        $request->merge(['remember' => false]);

        return $next($request);
    }
}
