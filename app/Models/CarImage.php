<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarImage extends Model {
    public $timestamps = false;
    protected $fillable = ['car_id', 'image', 'sort_order'];

    public function car(): BelongsTo {
        return $this->belongsTo(Car::class);
    }
}
