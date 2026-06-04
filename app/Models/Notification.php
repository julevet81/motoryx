<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model {
    public $timestamps = false;
    protected $fillable = ['tenant_id', 'user_id', 'title', 'body', 'type', 'data', 'read_at'];
    protected $casts = ['data' => 'array', 'read_at' => 'datetime', 'created_at' => 'datetime'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function isRead(): bool { return $this->read_at !== null; }
}
