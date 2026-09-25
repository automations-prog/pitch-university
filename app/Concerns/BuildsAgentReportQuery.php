<?php

namespace App\Concerns;

use App\Enums\LicenseStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait BuildsAgentReportQuery
{
    /**
     * Build the base query for agents shown in a progress report, applying
     * the optional search/status/license filters from the request.
     */
    protected function agentReportQuery(Request $request): Builder
    {
        $licenseId = $request->string('license')->toString();

        return User::query()
            ->where('role', UserRole::Agent)
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->string('status')->toString(), fn ($query, string $status) => $query->where('status', $status))
            ->when($licenseId, fn ($query, string $licenseId) => $query->whereHas(
                'licenses',
                fn ($licenses) => $licenses->whereKey($licenseId),
            ))
            ->with(['licenses' => fn ($query) => $query->where('status', LicenseStatus::Active)])
            ->orderBy('name');
    }
}
