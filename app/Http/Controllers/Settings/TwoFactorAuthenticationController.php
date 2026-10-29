<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Features;

class TwoFactorAuthenticationController extends Controller
{
    public function show(
        Request $request,
        EnableTwoFactorAuthentication $enableTwoFactorAuthentication
    ): Response {
        abort_unless(
            Features::enabled(Features::twoFactorAuthentication()),
            404
        );

        $user = $request->user();

        /*
         * Generate the secret exactly once.
         *
         * This fixes the condition previously diagnosed in GhanaDAMS:
         * USERS_WITH_2FA_SECRET=0.
         *
         * Existing secrets are preserved. Refreshing the page does NOT
         * rotate the secret.
         */
        if (empty($user->two_factor_secret)) {
            $enableTwoFactorAuthentication($user);
            $user->refresh();
        }

        return Inertia::render('settings/TwoFactor', [
            'twoFactorEnabled' => $user->two_factor_confirmed_at !== null,

            'requiresConfirmation' => Features::optionEnabled(
                Features::twoFactorAuthentication(),
                'confirm'
            ),
        ]);
    }

    /**
     * Restart an unfinished setup and generate one completely fresh QR.
     *
     * Confirmed accounts must be reset through the secure administrator
     * reset workflow instead of silently replacing an active factor.
     */
    public function restart(
        Request $request,
        EnableTwoFactorAuthentication $enableTwoFactorAuthentication
    ): RedirectResponse {
        $user = $request->user();

        abort_if(
            $user->two_factor_confirmed_at !== null,
            409,
            'Two-factor authentication is already confirmed. Use the secure 2FA reset process.'
        );

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        /*
         * Force a completely new secret.
         */
        $enableTwoFactorAuthentication($user, true);

        return back()->with(
            'success',
            'A fresh two-factor QR code has been generated.'
        );
    }
}