<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventBackHistoryCache
{
    /**
     * Handle an incoming request.
     *
     * Without this, the browser (or its back/forward cache) can serve a
     * previously rendered page from disk cache instead of hitting the
     * server — so after an admin leaves impersonation, navigating to a
     * page they'd already visited while impersonating could still show
     * the stale, impersonated view. Marking every response as
     * non-cacheable forces a fresh request (and therefore a fresh
     * `auth` prop) on every navigation.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}
