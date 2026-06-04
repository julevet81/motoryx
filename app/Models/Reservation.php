<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model {
    public $timestamps = false;
    protected $fillable = ['tenant_id', 'customer_id', 'car_id', 'reservation_date', 'deposit_amount', 'status', 'notes'];
    protected $casts = ['reservation_date' => 'datetime', 'deposit_amount' => 'decimal:2', 'created_at' => 'datetime'];

    public function tenant(): BelongsTo   { return $this->belongsTo(Tenant::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function car(): BelongsTo      { return $this->belongsTo(Car::class); }

    public function scopeForTenant($query, int $tenantId) {
        return $query->where('tenant_id', $tenantId);
    }
}
