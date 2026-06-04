<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model {
    protected $fillable = ['name', 'monthly_price', 'users_limit', 'cars_limit', 'branches_limit', 'is_active'];
    protected $casts = ['monthly_price' => 'decimal:2', 'is_active' => 'boolean'];

    public function subscriptions(): HasMany { return $this->hasMany(TenantSubscription::class, 'plan_id'); }
}
