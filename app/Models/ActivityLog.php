<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model {
    public $timestamps = false;
    protected $fillable = ['tenant_id', 'user_id', 'action', 'model_type', 'model_id', 'properties', 'ip_address', 'user_agent'];
    protected $casts = ['properties' => 'array', 'created_at' => 'datetime'];

    public function user(): BelongsTo   { return $this->belongsTo(User::class); }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
