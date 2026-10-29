<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ResetUserTwoFactorAuthenticationController extends Controller
{
    public function __invoke(
        Request $request,
        User $user
    ): RedirectResponse {
        $administrator = $request->user();

        abort_if($administrator === null, 401);

        /*
         * GhanaDAMS system administrators are represented by
         * user_status = IsSystem.
         */
        abort_unless(
            $administrator->user_status === 'IsSystem',
            403,
            'Only a system administrator can reset another user two-factor authentication.'
        );

        if ($administrator->is($user)) {
            throw ValidationException::withMessages([
                'email' => 'You cannot use the administrator reset action on your own account.',
            ]);
        }

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        /*
         * Require the administrator's CURRENT account password.
         */
        if (
            ! Hash::check(
                (string) $validated['password'],
                (string) $administrator->password
            )
        ) {
            throw ValidationException::withMessages([
                'password' => 'The administrator password is incorrect.',
            ]);
        }

        /*
         * Require the exact target email, as used by the LMS reset flow.
         */
        if (
            mb_strtolower(trim((string) $validated['email'])) !==
            mb_strtolower(trim((string) $user->email))
        ) {
            throw ValidationException::withMessages([
                'email' => 'Enter the exact email address of the user being reset.',
            ]);
        }

        /*
         * Remove the complete factor.
         *
         * The mandatory middleware will immediately require the target
         * user to enrol again. A new secret will be generated only when
         * they open the setup page.
         */
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return back()->with(
            'success',
            'Two-factor authentication has been reset for '.$user->email.'. The user must configure a new authenticator.'
        );
    }
}