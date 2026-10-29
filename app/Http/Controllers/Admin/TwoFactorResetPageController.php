<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorResetPageController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $administrator = $request->user();

        abort_if($administrator === null, 401);

        abort_unless(
            $administrator->user_status === 'IsSystem',
            403
        );

        $users = User::query()
            ->whereKeyNot($administrator->getKey())
            ->orderBy('name')
            ->orderBy('email')
            ->get([
                'id',
                'name',
                'email',
                'user_status',
                'two_factor_confirmed_at',
            ])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'user_status' => $user->user_status,
                'two_factor_enabled' => $user->two_factor_confirmed_at !== null,
            ])
            ->values();

        return Inertia::render('Admin/TwoFactorReset', [
            'users' => $users,
        ]);
    }
}