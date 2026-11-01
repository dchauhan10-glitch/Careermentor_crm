<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    /** Code prompt after a correct password. */
    public function challenge(Request $request)
    {
        abort_unless($request->session()->has('2fa:user_id'), 403);

        return view('auth.two-factor-challenge');
    }

    public function verify(Request $request)
    {
        $request->validate(['code' => ['required', 'string']]);
        $user = User::find($request->session()->get('2fa:user_id'));
        abort_unless($user && $user->is_active, 403);

        if (! Totp::verify($user->two_factor_secret, $request->input('code'))) {
            AuditLog::create(['action' => 'login.2fa_failed', 'ip_address' => $request->ip(), 'details' => ['user_id' => $user->id]]);
            throw ValidationException::withMessages(['code' => 'That code is not correct. Please try the newest code in your app.']);
        }

        $request->session()->forget('2fa:user_id');

        return LoginController::complete($request, $user);
    }

    /** Setting up the authenticator app (required for the Super Admin). */
    public function setup(Request $request)
    {
        $user = $request->user();
        $secret = $request->session()->get('2fa:new_secret') ?? Totp::newSecret();
        $request->session()->put('2fa:new_secret', $secret);

        return view('auth.two-factor-setup', [
            'secret' => trim(chunk_split($secret, 4, ' ')),
            'uri' => Totp::uri($secret, $user->email),
        ]);
    }

    public function confirm(Request $request)
    {
        $request->validate(['code' => ['required', 'string']]);
        $secret = $request->session()->get('2fa:new_secret');
        if (! $secret || ! Totp::verify($secret, $request->input('code'))) {
            throw ValidationException::withMessages(['code' => 'That code is not correct. Check the key was typed correctly and try again.']);
        }

        $user = $request->user();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();
        $request->session()->forget('2fa:new_secret');
        $request->session()->put('2fa:passed', true);
        AuditLog::record('2fa.enabled', $user);

        return redirect()->route('dashboard')->with('status', 'Two-step login is now on.');
    }
}
