<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireFeature
{
    public function handle(Request $request, Closure $next, string $feature): mixed
    {
        abort_unless(feature($feature), 404);

        return $next($request);
    }
}
