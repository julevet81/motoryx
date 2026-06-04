<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionStatus extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];

    public function subscriptions()
    {
        return $this->hasMany(TenantSubscription::class, 'status_id');
    }
}
