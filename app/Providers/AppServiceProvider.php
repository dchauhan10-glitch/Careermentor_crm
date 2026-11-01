<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Password::defaults(fn () => Password::min(10)->letters()->numbers()->uncompromised());

        // Super Admin-only areas: users, teams, settings, import, export, audit.
        Gate::define('admin', fn (User $u) => $u->isSuperAdmin());
        // Team reports and assignment screens.
        Gate::define('lead-team', fn (User $u) => $u->isSuperAdmin() || $u->isTeamLead());

        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(5)->by(mb_strtolower((string) $r->input('email')).'|'.$r->ip()),
            Limit::perMinute(20)->by($r->ip()),
        ]);
    }
}
