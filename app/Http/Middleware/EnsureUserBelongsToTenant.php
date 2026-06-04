<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures authenticated user belongs to the current tenant.
 * Critical security layer against cross-tenant access.
 */
class EnsureUserBelongsToTenant
{
    use ApiResponser;

    public function handle(Request $request, Closure $next): Response
    {
        $user   = $request->user();
        $tenant = app('tenant');

        if (! $user || (int) $user->tenant_id !== (int) $tenant->id) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        if (! $user->isActive()) {
            return $this->errorResponse('Your account has been suspended.', 403);
        }

        return $next($request);
    }
}
