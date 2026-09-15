<?php

namespace App\Concerns;

use App\Enums\LicenseStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\VerticalTraining;
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

    /**
     * Deterministic sample progress for an agent, seeded by their id (and the
     * selected training, when filtering to one) so the numbers stay stable
     * across reloads instead of changing every request.
     *
     * Not backed by real roleplay attempts yet — placeholder until AI
     * scoring is wired up.
     *
     * @return array<string, int|string|null>
     */
    protected function mockProgressFor(User $agent, int $totalTrainings, ?VerticalTraining $training = null): array
    {
        $seed = crc32($agent->id.'-'.($training?->id ?? 'all'));

        if ($training) {
            $totalTrainings = 1;
            $completed = $seed % 2;
        } else {
            $completed = $totalTrainings > 0 ? $seed % ($totalTrainings + 1) : 0;
        }

        $averageScore = $completed > 0 ? 55 + ($seed % 46) : null;
        $lastActivity = $completed > 0 ? now()->subDays($seed % 30)->toDateString() : null;

        return [
            'trainings_completed' => $completed,
            'total_trainings' => $totalTrainings,
            'average_score' => $averageScore,
            'last_activity' => $lastActivity,
        ];
    }
}
