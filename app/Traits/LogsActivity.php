<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

trait LogsActivity
{
    protected function logActivity(
        string $action,
        ?object $model = null,
        array $properties = []
    ): void {
        try {
            /** @var Request $request */
            $request  = request();
            $user     = $request->user();
            $tenant   = app('tenant') ?? null;

            ActivityLog::create([
                'tenant_id'  => $tenant?->id,
                'user_id'    => $user?->id,
                'action'     => $action,
                'model_type' => $model ? get_class($model) : null,
                'model_id'   => $model?->id,
                'properties' => $properties ?: null,
                'ip_address' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 255),
            ]);
        } catch (\Throwable) {
            // Never let logging break the request
        }
    }
}
