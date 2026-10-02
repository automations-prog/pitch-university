<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // URL::forceScheme('https');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->configureDevCommands();
    }

    /**
     * `composer run dev` only listens on the default queue; delivery
     * scoring runs on its own `roleplay` queue.
     */
    protected function configureDevCommands(): void
    {
        DevCommands::artisan('queue:listen --queue=roleplay --tries=1 --timeout=0', 'roleplay-queue');
    }

    /**
     * Every roleplay session becomes a paid Realtime call, so cap how many
     * a user can start per day.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('roleplay-sessions', fn (Request $request): Limit => Limit::perDay(
            config('services.openai.roleplay_daily_limit'),
        )->by($request->user()->id));
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        // Inertia pages consume resources as plain objects, not { data: ... }.
        JsonResource::withoutWrapping();

        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn (): ?Password => app()->isProduction()
                ? Password::min(12)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols()
                    ->uncompromised()
                : null,
        );
    }
}
