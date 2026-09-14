<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLicenseStepRequest;
use App\Http\Requests\Admin\UpdateLicenseStepRequest;
use App\Models\License;
use App\Models\LicenseStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class LicenseStepController extends Controller
{
    /**
     * Store a newly created instruction step.
     */
    public function store(StoreLicenseStepRequest $request, License $licensing): RedirectResponse
    {
        $nextOrder = $licensing->steps()->max('order');

        $licensing->steps()->create([
            ...$request->validated(),
            'order' => $nextOrder === null ? 0 : $nextOrder + 1,
        ]);

        return to_route('admin.licensing.edit', $licensing);
    }

    /**
     * Update the given instruction step.
     */
    public function update(UpdateLicenseStepRequest $request, License $licensing, LicenseStep $step): RedirectResponse
    {
        $step->update($request->validated());

        return to_route('admin.licensing.edit', $licensing);
    }

    /**
     * Remove the given instruction step.
     */
    public function destroy(License $licensing, LicenseStep $step): RedirectResponse
    {
        Gate::authorize('update', $licensing);

        $step->delete();

        return to_route('admin.licensing.edit', $licensing);
    }

    /**
     * Move the given instruction step up or down relative to its siblings.
     */
    public function move(Request $request, License $licensing, LicenseStep $step): RedirectResponse
    {
        Gate::authorize('update', $licensing);

        $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ]);

        $steps = $licensing->steps()->orderBy('order')->get();
        $index = $steps->search(fn (LicenseStep $candidate) => $candidate->is($step));
        $swapIndex = $request->string('direction')->toString() === 'up' ? $index - 1 : $index + 1;

        if ($index !== false && $steps->has($swapIndex)) {
            $sibling = $steps->get($swapIndex);

            [$stepOrder, $siblingOrder] = [$step->order, $sibling->order];

            $step->update(['order' => $siblingOrder]);
            $sibling->update(['order' => $stepOrder]);
        }

        return to_route('admin.licensing.edit', $licensing);
    }
}
