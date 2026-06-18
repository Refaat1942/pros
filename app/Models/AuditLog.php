<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سجل الرقابة الحصين — Append-Only Audit Log
 */
class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'user_name',
        'action',
        'description',
        'tag',
        'ip_address',
        'mac_address',
        'payload_before',
        'payload_after',
        'logged_at',
    ];

    protected $casts = [
        'payload_before' => 'array',
        'payload_after' => 'array',
        'logged_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
