<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CarFeature extends Model {
    public $timestamps = false;
    protected $fillable = ['name'];

    public function cars(): BelongsToMany {
        return $this->belongsToMany(Car::class, 'car_feature_car', 'feature_id', 'car_id');
    }
}
