<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class ImpersonateController extends Controller
{
    /**
     * Stop impersonating and return to the original admin session.
     *
     * Forces a full-page reload instead of a client-side Inertia visit, so
     * any prefetched pages cached while impersonating (which carry the
     * impersonated user's auth props) are discarded rather than served
     * stale after the session is back to the admin.
     */
    public function leave(Request $request): Response
    {
        $request->user()->leaveImpersonation();

        return Inertia::location(route('admin.users.index'));
    }
}
