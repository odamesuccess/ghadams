<?php

namespace App\Http\Responses;

use App\Services\RoleLandingService;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class RoleAwareLoginResponse implements LoginResponseContract
{
    public function __construct(private readonly RoleLandingService $landing) {}

    public function toResponse($request)
    {
        $user = $request->user();
        if ($user && $this->landing->isSpecialRole($user)) {
            $destination = $this->landing->destinationUrl($user);
            abort_if($destination === null, 403, 'This special role has no permitted landing page. Ask an administrator to update the role.');
            // Never let an old intended dashboard URL override a special workspace.
            $request->session()->forget('url.intended');
            if ($request->wantsJson()) return response()->json(['two_factor' => false, 'redirect' => $destination]);
            return redirect()->to($destination);
        }
        if ($request->wantsJson()) return response()->json(['two_factor' => false]);
        return redirect()->intended(Fortify::redirects('login', config('fortify.home', '/dashboard')));
    }
}
