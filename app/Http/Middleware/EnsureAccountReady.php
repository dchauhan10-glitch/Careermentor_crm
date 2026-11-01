<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs out disabled staff at once, and makes the Super Admin set up
 * two-step login before using the CRM.
 */
class EnsureAccountReady
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user->is_active || $user->trashed()) {
            Auth::logout();
            $request->session()->invalidate();

            return redirect()->route('login')->withErrors(['email' => 'This account has been disabled.']);
        }

        $onSetup = $request->routeIs('two-factor.setup', 'two-factor.confirm', 'logout');

        if ($user->isSuperAdmin() && ! $user->hasTwoFactorEnabled() && ! $onSetup && config('crm.require_admin_2fa')) {
            return redirect()->route('two-factor.setup');
        }

        return $next($request);
    }
}
