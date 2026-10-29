<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceSingleRoleAssignment
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $isCreate = $request->routeIs('users.store');
        $isUpdate = $request->routeIs('users.update');

        if ($isCreate || $isUpdate) {
            $request->validate(
                [
                    'roles' => [
                        'required',
                        'array',
                        'size:1',
                    ],

                    'roles.0' => [
                        'required',
                        'string',
                        'exists:roles,name',
                    ],
                ],
                [
                    'roles.required' =>
                        'Select one role.',

                    'roles.size' =>
                        'Each GhanaDAMS user must have exactly one role.',
                ]
            );
        }

        return $next($request);
    }
}