<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCallLogRequest;
use App\Models\CallLog;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CallLogController extends Controller
{
    /**
     * Update the given call log's manager/HR notes.
     */
    public function update(UpdateCallLogRequest $request, CallLog $callLog): RedirectResponse
    {
        $callLog->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Notes saved.')]);

        return back();
    }
}
