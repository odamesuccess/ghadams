<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Models\User;
use App\Services\LoginTrackerService;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Illuminate\Validation\ValidationException;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        if (! $user->canLogin()) {
            LoginTrackerService::record(
                request(),
                $user->isExpired() ? 'expired_blocked' : 'inactive_blocked',
                $user,
                $user->email,
                'Password reset blocked because account is inactive or expired.'
            );

            throw ValidationException::withMessages([
                'email' => __('This account is inactive or expired. Please contact the administrator.'),
            ]);
        }

        $user->forceFill([
            'password' => $input['password'],
            'force_password_change' => false,
            'password_changed_time' => now(),
        ])->save();

        LoginTrackerService::record(
            request(),
            'password_reset_completed',
            $user,
            $user->email,
            'User completed password reset.'
        );
    }
}
