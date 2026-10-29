<?php

namespace App\Http\Responses;

use App\Services\RoleLandingService;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Fortify;

class RoleAwareTwoFactorLoginResponse implements TwoFactorLoginResponseContract
{
    public function toResponse($request)
    {
        $landing = app(RoleLandingService::class);
        $user = $request->user();
        if ($user && $landing->isSpecialRole($user)) {
            $destination = $landing->destinationUrl($user);
            abort_if($destination === null, 403, 'This special role has no permitted landing page. Ask an administrator to update the role.');
            $request->session()->forget('url.intended');
            if ($request->wantsJson()) return response()->json(['redirect' => $destination]);
            return redirect()->to($destination);
        }
        if ($request->wantsJson()) return response('', 204);
        return redirect()->intended(Fortify::redirects('login', config('fortify.home', '/dashboard')));
    }
}
