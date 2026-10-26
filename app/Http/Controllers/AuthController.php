<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function login(Request $r)
    {
        $data = $r->validate(['email' => 'required|string|email|max:255', 'password' => 'required|string|max:255']);
        $key = 'login:'.hash('sha256', strtolower($data['email']).'|'.$r->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'ログイン試行が多すぎます。1分ほど待って再試行してください。']);
        }
        if (! Auth::attempt($data)) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'メールアドレスまたはパスワードが違います。']);
        }
        RateLimiter::clear($key);
        $r->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect()->route('login');
    }
}
