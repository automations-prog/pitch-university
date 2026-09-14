<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImpersonateController extends Controller
{
    /**
     * Stop impersonating and return to the original admin session.
     */
    public function leave(Request $request): RedirectResponse
    {
        $request->user()->leaveImpersonation();

        return to_route('admin.users.index');
    }
}
