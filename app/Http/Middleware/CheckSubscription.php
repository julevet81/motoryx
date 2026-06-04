<?php

namespace App\Http\Middleware;

use App\Traits\ApiResponser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks requests when tenant subscription is expired or missing.
 */
class CheckSubscription
{
    use ApiResponser;

    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\Tenant $tenant */
        $tenant = app('tenant');

        if (! $tenant->hasActiveSubscription()) {
            return $this->errorResponse(
                'Your subscription has expired. Please renew to continue.',
                402
            );
        }

        return $next($request);
    }
}
