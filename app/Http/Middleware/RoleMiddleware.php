<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Allow the request only if the authenticated user has one of $roles.
     *
     * Usage: ->middleware('role:admin')  or  'role:teacher,admin'
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole($roles)) {
            abort(403, 'You are not authorized to access this page.');
        }

        return $next($request);
    }
}
