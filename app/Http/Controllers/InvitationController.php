<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Enums\WorkLocation;
use App\Http\Requests\AcceptInvitationRequest;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    /**
     * Show the sign-up form for a team invite link, or why it can't be
     * used any more.
     */
    public function show(TeamInvitation $invitation): Response
    {
        $invitation->load('team.campaign:id,name');

        return Inertia::render('auth/invitation', [
            'invitation' => [
                'token' => $invitation->token,
                'team' => $invitation->team->name,
                'campaign' => $invitation->team->campaign->name,
                'role' => $invitation->role,
                'status' => $invitation->status(),
            ],
            'workLocations' => WorkLocation::cases(),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Create the account on the link's team and sign it in. The link is
     * locked while it's used so a last remaining use can't be taken twice.
     */
    public function store(AcceptInvitationRequest $request, TeamInvitation $invitation): RedirectResponse
    {
        $user = DB::transaction(function () use ($request, $invitation) {
            $invitation = TeamInvitation::whereKey($invitation->id)->lockForUpdate()->firstOrFail();

            if (! $invitation->isUsable()) {
                throw ValidationException::withMessages([
                    'invitation' => __('This invite link can no longer be used.'),
                ]);
            }

            $invitation->increment('uses_count');

            return User::create([
                ...$request->safe()->only(['name', 'email', 'password', 'work_location']),
                'role' => $invitation->role,
                'status' => UserStatus::Active,
                'team_id' => $invitation->team_id,
            ]);
        });

        Auth::login($user);
        $request->session()->regenerate();

        return to_route('dashboard');
    }
}
