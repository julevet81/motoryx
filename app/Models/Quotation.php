<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'quotation_number', 'customer_id', 'salesperson_id',
        'subtotal', 'discount', 'total', 'status', 'valid_until',
    ];

    protected $casts = [
        'valid_until' => 'date',
        'subtotal'    => 'decimal:2',
        'discount'    => 'decimal:2',
        'total'       => 'decimal:2',
    ];

    public function tenant(): BelongsTo      { return $this->belongsTo(Tenant::class); }
    public function customer(): BelongsTo    { return $this->belongsTo(Customer::class); }
    public function salesperson(): BelongsTo { return $this->belongsTo(User::class, 'salesperson_id'); }
    public function items(): HasMany         { return $this->hasMany(QuotationItem::class); }

    public function isExpired(): bool
    {
        return $this->valid_until && $this->valid_until->isPast();
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
