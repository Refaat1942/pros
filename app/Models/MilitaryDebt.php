<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سجل مديونية جهة عسكرية — يُنشأ تلقائياً عند إغلاق الحالة العسكرية بالتسليم.
 * مدنيون مستبعدون تماماً — المسار العسكري فقط.
 */
class MilitaryDebt extends Model
{
    public const STATUS_PENDING    = 'pending_collection';
    public const STATUS_PARTIAL    = 'partial_collection';
    public const STATUS_COLLECTED  = 'collected';

    protected $fillable = [
        'case_id',
        'work_order_no',
        'patient_name',
        'patient_national_id',
        'sovereign_entity',
        'total_cost',
        'collected',
        'delivered_at',
        'status',
        'collected_at',
    ];

    protected $casts = [
        'total_cost'    => 'decimal:2',
        'collected'     => 'decimal:2',
        'delivered_at'  => 'date',
        'collected_at'  => 'datetime',
    ];

    public function caseRecord(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCollected(): bool
    {
        return $this->status === self::STATUS_COLLECTED;
    }

    public function remainingAmount(): float
    {
        return max(0, round((float) $this->total_cost - (float) $this->collected, 2));
    }
}
