<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function index()
    {
        return view('admin.profile.index');
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:filter', 'max:255', 'unique:users,email,'.$user->id],
        ]);

        $user->fill([
            'name' => $validated['name'],
            'email' => strtolower(trim($validated['email'])),
            'normalized_email' => strtolower(trim($validated['email'])),
        ])->save();

        return back()->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        // Plain password — the model "hashed" cast hashes exactly once.
        $user->password = $validated['password'];
        $user->save();

        // Re-hash via logoutOtherDevices to invalidate other sessions / remember tokens.
        Auth::logoutOtherDevices($validated['password']);

        $request->session()->regenerate();

        return back()->with('success', 'Password changed successfully. Other sessions were signed out.');
    }
}
