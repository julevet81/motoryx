<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleCategory extends Model {
    protected $fillable = ['name'];
    public function cars(): HasMany { return $this->hasMany(Car::class, 'category_id'); }
}
