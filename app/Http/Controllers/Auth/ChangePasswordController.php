<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PasswordHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class ChangePasswordController extends Controller
{
    /**
     * Show force change password page
     */
    public function edit()
    {
        return Inertia::render('auth/ChangePassword');
    }

    /**
     * Update password and store history
     */
    public function update(Request $request)
    {
        $request->validate([
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $user = Auth::user();

        /*
        |-------------------------------------------------
        | Prevent reuse of last 5 passwords
        |-------------------------------------------------
        */
        $recentPasswords = PasswordHistory::where('user_id', $user->id)
            ->latest()
            ->take(5)
            ->pluck('password');

        foreach ($recentPasswords as $oldPassword) {
            if (Hash::check($request->password, $oldPassword)) {
                throw ValidationException::withMessages([
                    'password' => 'You cannot reuse your last 5 passwords.',
                ]);
            }
        }

        /*
        |-------------------------------------------------
        | Update user password
        |-------------------------------------------------
        */
        $user->password = Hash::make($request->password);
        $user->force_password_change = false;
        $user->password_changed_time = now();
        $user->save();

        /*
        |-------------------------------------------------
        | Store password history
        |-------------------------------------------------
        */
        PasswordHistory::create([
            'user_id' => $user->id,
            'password' => $user->password, // already hashed
        ]);

        /*
        |-------------------------------------------------
        | Keep only last 5 passwords
        |-------------------------------------------------
        */
        PasswordHistory::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->skip(5)
            ->delete();

        Auth::logout();

        return redirect()
            ->route('login')
            ->with('status', 'Password changed successfully. Please log in again.');
    }
}
