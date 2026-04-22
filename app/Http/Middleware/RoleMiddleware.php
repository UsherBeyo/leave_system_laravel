<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if (! $user) return redirect()->route('login');
        if (! in_array((string) $user->role, $roles, true)) abort(403, 'Access denied.');
        return $next($request);
    }
}
