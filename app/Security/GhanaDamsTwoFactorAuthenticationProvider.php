<?php

namespace App\Security;

use Laravel\Fortify\TwoFactorAuthenticationProvider as FortifyTwoFactorAuthenticationProvider;

class GhanaDamsTwoFactorAuthenticationProvider extends FortifyTwoFactorAuthenticationProvider
{
    /**
     * GhanaDAMS always submits one six-digit string to Fortify,
     * although the interface displays six separate OTP boxes.
     */
    public function verify($secret, $code)
    {
        $code = preg_replace('/\D+/', '', (string) $code) ?? '';

        if (strlen($code) !== 6) {
            return false;
        }

        return parent::verify($secret, $code);
    }
}