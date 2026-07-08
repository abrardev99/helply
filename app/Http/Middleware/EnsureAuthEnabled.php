<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;
use Symfony\Component\HttpFoundation\Response;

class EnsureAuthEnabled
{
    /**
     * Block every account flow (login, registration, password reset, etc.) unless
     * the "auth" feature is active for the current environment.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Feature::active('auth'), 404);

        return $next($request);
    }
}
