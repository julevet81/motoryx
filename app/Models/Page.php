<?php namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Page extends Model {
    protected $fillable = ['tenant_id', 'title', 'slug', 'content', 'is_published'];
    protected $casts = ['is_published' => 'boolean'];
    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
}
