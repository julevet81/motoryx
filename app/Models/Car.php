<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Car extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'branch_id', 'brand_id', 'model_id', 'category_id',
        'vin', 'registration_number', 'year', 'color',
        'fuel_type', 'transmission', 'mileage', 'engine_size',
        'horsepower', 'seats', 'doors', 'condition',
        'purchase_price', 'selling_price', 'discounted_price',
        'description', 'status', 'published_at',
    ];

    protected $casts = [
        'published_at'     => 'datetime',
        'purchase_price'   => 'decimal:2',
        'selling_price'    => 'decimal:2',
        'discounted_price' => 'decimal:2',
        'engine_size'      => 'decimal:1',
        'mileage'          => 'integer',
    ];

    // -------------------------------------------------------
    // Relations
    // -------------------------------------------------------

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(CarModel::class, 'model_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(VehicleCategory::class, 'category_id');
    }

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(CarFeature::class, 'car_feature_car', 'car_id', 'feature_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(CarImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): ?CarImage
    {
        return $this->images()->first();
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    // -------------------------------------------------------
    // Accessors
    // -------------------------------------------------------

    public function getEffectivePriceAttribute(): ?float
    {
        return $this->discounted_price ?? $this->selling_price;
    }

    // -------------------------------------------------------
    // Scopes
    // -------------------------------------------------------

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopePublished($query)
    {
        return $query->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeFilter($query, array $filters)
    {
        return $query
            ->when($filters['brand_id'] ?? null,    fn($q, $v) => $q->where('brand_id', $v))
            ->when($filters['model_id'] ?? null,    fn($q, $v) => $q->where('model_id', $v))
            ->when($filters['category_id'] ?? null, fn($q, $v) => $q->where('category_id', $v))
            ->when($filters['year_from'] ?? null,   fn($q, $v) => $q->where('year', '>=', $v))
            ->when($filters['year_to'] ?? null,     fn($q, $v) => $q->where('year', '<=', $v))
            ->when($filters['price_from'] ?? null,  fn($q, $v) => $q->where('selling_price', '>=', $v))
            ->when($filters['price_to'] ?? null,    fn($q, $v) => $q->where('selling_price', '<=', $v))
            ->when($filters['fuel_type'] ?? null,   fn($q, $v) => $q->where('fuel_type', $v))
            ->when($filters['transmission'] ?? null, fn($q, $v) => $q->where('transmission', $v))
            ->when($filters['condition'] ?? null,   fn($q, $v) => $q->where('condition', $v))
            ->when($filters['status'] ?? null,      fn($q, $v) => $q->where('status', $v))
            ->when($filters['search'] ?? null,      fn($q, $v) => $q->where(function ($q) use ($v) {
                $q->whereHas('brand', fn($b) => $b->where('name', 'like', "%{$v}%"))
                  ->orWhereHas('model', fn($m) => $m->where('name', 'like', "%{$v}%"))
                  ->orWhere('vin', 'like', "%{$v}%")
                  ->orWhere('registration_number', 'like', "%{$v}%");
            }));
    }
}
