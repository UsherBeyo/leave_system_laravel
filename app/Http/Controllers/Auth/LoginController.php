<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'agree_privacy' => ['accepted'],
        ], [
            'agree_privacy.accepted' => 'You must agree to the Data Privacy and Terms to login.',
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (!$user || !$user->is_active || !Hash::check($credentials['password'], $user->password)) {
            return back()
                ->withErrors(['email' => 'Invalid email or password.'])
                ->onlyInput('email');
        }

        Auth::login($user);
        $request->session()->regenerate();

        $employeeId = Employee::query()->where('user_id', $user->id)->value('id');
        if ($employeeId) {
            $request->session()->put('emp_id', (int) $employeeId);
        } else {
            $request->session()->forget('emp_id');
        }

        return redirect()->intended(route('dashboard'))->with('success', 'Login successful');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'You have been logged out.');
    }
}
