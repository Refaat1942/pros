<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * إشعار داخل التطبيق — مستهدف بدور/لوحة، يُعرض في جرس الإشعارات.
 */
class AppNotification extends Model
{
    protected $fillable = [
        'role_slug',
        'case_id',
        'event',
        'title',
        'body',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    public function caseRecord(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }

    public function scopeForRole(Builder $query, string $roleSlug): Builder
    {
        return $query->where('role_slug', $roleSlug);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }
}
