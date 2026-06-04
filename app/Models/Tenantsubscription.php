<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantSubscription extends Model {
    protected $fillable = ['tenant_id', 'plan_id', 'start_date', 'end_date', 'status'];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function plan(): BelongsTo   { return $this->belongsTo(SubscriptionPlan::class, 'plan_id'); }

    public function isActive(): bool {
        return $this->status === 'active' && $this->end_date->isFuture();
    }
}
