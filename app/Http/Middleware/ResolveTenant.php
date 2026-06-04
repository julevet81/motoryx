<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Traits\ApiResponser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves tenant from subdomain or X-Tenant header.
 * Injects tenant into request and app container for the lifetime of the request.
 */
class ResolveTenant
{
    use ApiResponser;

    public function handle(Request $request, Closure $next): Response
    {
        $slug = $this->resolveTenantSlug($request);

        if (! $slug) {
            return $this->errorResponse('Tenant not specified.', 400);
        }

        // Cache tenant for 5 minutes to reduce DB hits
        $tenant = Cache::remember("tenant:{$slug}", 300, fn() =>
            Tenant::where('slug', $slug)->first()
        );

        if (! $tenant) {
            return $this->errorResponse('Tenant not found.', 404);
        }

        if (! $tenant->is_active) {
            return $this->errorResponse('This account is suspended.', 403);
        }

        // Make tenant accessible everywhere this request
        app()->instance('tenant', $tenant);
        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }

    private function resolveTenantSlug(Request $request): ?string
    {
        // Priority 1: X-Tenant header
        if ($header = $request->header('X-Tenant')) {
            return strtolower(trim($header));
        }

        // Priority 2: subdomain  (e.g. acme.yourapp.com)
        $host = $request->getHost();
        $parts = explode('.', $host);
        if (count($parts) >= 3) {
            return strtolower($parts[0]);
        }

        // Priority 3: route parameter
        return $request->route('tenant');
    }
}
