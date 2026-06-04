<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model {
    public $timestamps = false;
    protected $fillable = ['quotation_id', 'car_id', 'unit_price', 'discount', 'total'];
    protected $casts = ['unit_price' => 'decimal:2', 'discount' => 'decimal:2', 'total' => 'decimal:2'];

    public function quotation(): BelongsTo { return $this->belongsTo(Quotation::class); }
    public function car(): BelongsTo       { return $this->belongsTo(Car::class); }
}
