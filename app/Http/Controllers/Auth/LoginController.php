<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', mb_strtolower($data['email']))->first();
        if (! $user || ! $user->is_active || ! Auth::validate(['email' => $user->email, 'password' => $data['password']])) {
            AuditLog::create(['action' => 'login.failed', 'ip_address' => $request->ip(), 'details' => ['user_id' => $user?->id]]);
            throw ValidationException::withMessages(['email' => 'Email or password is not correct, or the account is disabled.']);
        }

        $request->session()->regenerate();

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put('2fa:user_id', $user->id);

            return redirect()->route('two-factor.challenge');
        }

        return $this->complete($request, $user);
    }

    public static function complete(Request $request, User $user)
    {
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('2fa:passed', $user->hasTwoFactorEnabled());
        $user->forceFill(['last_login_at' => now()])->save();
        AuditLog::record('login.success', $user);

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        AuditLog::record('logout');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
