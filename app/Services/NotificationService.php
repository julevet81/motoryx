<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class NotificationService
{
    /**
     * Send a notification to a single user.
     */
    public function send(User $user, string $title, string $body, string $type = 'general', array $data = []): Notification
    {
        return Notification::create([
            'tenant_id' => $user->tenant_id,
            'user_id'   => $user->id,
            'title'     => $title,
            'body'      => $body,
            'type'      => $type,
            'data'      => $data ?: null,
        ]);
    }

    /**
     * Broadcast a notification to all users of a tenant.
     */
    public function broadcast(int $tenantId, string $title, string $body, string $type = 'general'): void
    {
        $users = User::forTenant($tenantId)->active()->pluck('id');

        $now  = now();
        $rows = $users->map(fn($id) => [
            'tenant_id'  => $tenantId,
            'user_id'    => $id,
            'title'      => $title,
            'body'       => $body,
            'type'       => $type,
            'created_at' => $now,
        ])->toArray();

        // Chunk inserts to avoid hitting DB parameter limits
        foreach (array_chunk($rows, 500) as $chunk) {
            Notification::insert($chunk);
        }
    }

    /**
     * Notify a lead has been assigned.
     */
    public function notifyLeadAssigned(User $assignee, int $leadId, int $customerId): void
    {
        $this->send(
            $assignee,
            'New Lead Assigned',
            "A lead has been assigned to you. Lead #{$leadId}",
            'lead_assigned',
            ['lead_id' => $leadId, 'customer_id' => $customerId]
        );
    }

    /**
     * Notify manager of a new sale.
     */
    public function notifySaleCreated(int $tenantId, string $saleNumber, float $total): void
    {
        User::forTenant($tenantId)
            ->where('role', 'admin')
            ->active()
            ->get()
            ->each(fn($admin) => $this->send(
                $admin,
                'New Sale Created',
                "Sale {$saleNumber} totaling " . number_format($total, 2) . " has been recorded.",
                'sale_created',
                ['sale_number' => $saleNumber, 'total' => $total]
            ));
    }
}
