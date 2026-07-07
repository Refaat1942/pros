<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * إشعار دائن — clinic_credit_notes (مسار مدني فقط بعد التسليم)
 */
class CreditNote extends Model
{
    public const TYPE_PARTIAL = 'partial';

    public const TYPE_FULL = 'full';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'credit_note_no',
        'case_id',
        'order_ref',
        'patient_name',
        'company_name',
        'type',
        'amount',
        'original_total',
        'reason',
        'status',
        'approved_at',
        'approved_by',
        'approved_by_user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'original_total' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function caseRecord(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }
}
