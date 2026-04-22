<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) return redirect()->route('dashboard');
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required','email'],
            'password' => ['required','string'],
            'agree_privacy' => ['accepted'],
        ], ['agree_privacy.accepted' => 'You must agree to the Data Privacy and Terms.']);

        if (Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password'], 'is_active' => 1])) {
            $request->session()->regenerate();
            return redirect()->intended(route('dashboard'))->with('success', 'Login successful');
        }

        return back()->withInput($request->only('email'))->withErrors(['email' => 'Invalid email or password']);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login')->with('success', 'Logged out successfully');
    }
}
