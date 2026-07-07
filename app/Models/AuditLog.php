<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * سجل الرقابة الحصين — Append-Only Audit Log
 *
 * هذا النموذج للإضافة فقط: UPDATE و DELETE محظوران بصرامة.
 * استخدم AuditService::log() لكتابة الصفوف.
 */
class AuditLog extends Model
{
    /**
     * منع أي تعديل على صفوف موجودة.
     */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('AuditLog records are immutable — use AuditService::log() or AuditLog::create() only.');
        }

        return parent::save($options);
    }

    /**
     * منع حذف صفوف الرقابة نهائياً.
     */
    public function delete(): ?bool
    {
        throw new LogicException('AuditLog records cannot be deleted — the audit trail is permanent.');
    }

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
