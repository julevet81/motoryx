<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model {
    protected $fillable = ['name', 'logo'];

    public function carModels(): HasMany {
        return $this->hasMany(CarModel::class);
    }
    public function cars(): HasMany {
        return $this->hasMany(Car::class);
    }
}
