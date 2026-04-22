<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ChangePasswordController extends Controller
{
    public function edit(): View
    {
        return view('settings.change-password');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min(6)],
        ]);

        $user = Auth::user();
        if (!$user || !Hash::check((string) $data['current_password'], (string) $user->password)) {
            return back()->withInput($request->except('current_password', 'password', 'password_confirmation'))
                ->with('error', 'Current password incorrect.');
        }

        $user->password = (string) $data['password'];
        $user->save();

        return redirect()->route('dashboard')->with('success', 'Password updated successfully.');
    }
}
