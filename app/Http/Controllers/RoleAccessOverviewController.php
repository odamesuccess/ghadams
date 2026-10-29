<?php

namespace App\Http\Controllers;

use App\Services\RoleLandingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RoleAccessOverviewController extends Controller
{
    public function __construct(
        private readonly
        RoleLandingService $landing
    ) {
    }

    public function index(
        Request $request
    ): Response {
        $this->authorizeSuperAdmin(
            $request
        );

        $roles = Role::query()
            ->with([
                'permissions:id,name',
                'users:id,name,email',
            ])
            ->orderByRaw(
                'CASE WHEN name = ? THEN 0 ELSE 1 END',
                [
                    RoleLandingService::SPECIAL_ROLE_NAME,
                ]
            )
            ->orderBy('name')
            ->get()
            ->map(
                function (Role $role): array {
                    $isSpecial =
                        $this->landing->isSpecialDefinition($role);

                    return [
                        'id' =>
                            $role->id,

                        'name' =>
                            $role->name,

                        'is_special_role' =>
                            $isSpecial,

                        'landing_route' =>
                            $isSpecial
                                ? $role->landing_route
                                : null,

                        'landing_options' =>
                            $isSpecial
                                ? $this->landing
                                    ->landingOptions(
                                        $role
                                    )
                                : [],

                        'permission_count' =>
                            $role
                                ->permissions
                                ->count(),

                        'user_count' =>
                            $role
                                ->users
                                ->count(),

                        'permissions' =>
                            $role
                                ->permissions
                                ->pluck('name')
                                ->values(),

                        'users' =>
                            $role
                                ->users
                                ->map(
                                    fn ($user) => [
                                        'id' =>
                                            $user->id,

                                        'name' =>
                                            $user->name,

                                        'email' =>
                                            $user->email,
                                    ]
                                )
                                ->values(),
                    ];
                }
            )
            ->values();

        return Inertia::render(
            'Roles/AccessOverview',
            [
                'roles' =>
                    $roles,
            ]
        );
    }

    public function update(
        Request $request,
        Role $role
    ): RedirectResponse {
        $this->authorizeSuperAdmin(
            $request
        );

        abort_unless(
            $this->landing->isSpecialDefinition($role),
            403,
            'Only Special Role can have a custom landing page.'
        );

        $validated =
            $request->validate([
                'landing_route' => [
                    'required',
                    'string',
                    'max:255',
                ],
            ]);

        $landingRoute =
            trim(
                (string)
                $validated['landing_route']
            );

        if (
            ! $this->landing
                ->roleCanUseLanding(
                    $role,
                    $landingRoute
                )
        ) {
            throw ValidationException::withMessages([
                'landing_route' =>
                    'The selected landing page is not backed by a permission assigned to Special Role.',
            ]);
        }

        $role->forceFill([
            'landing_route' =>
                $landingRoute,
        ])->save();

        return back()->with(
            'success',
            'Special Role landing page updated successfully.'
        );
    }

    private function authorizeSuperAdmin(
        Request $request
    ): void {
        $user = $request->user();

        abort_if(
            $user === null,
            401
        );

        abort_unless(
            $user->hasRole('Super Admin'),
            403,
            'Only Super Admin can manage the Special Role landing page.'
        );
    }
}