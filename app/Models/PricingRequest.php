<?php

namespace App\Models;

use App\Enums\PricingRequestStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * طلب التسعير — clinic_pricing_queue
 */
class PricingRequest extends Model
{
    public const STEP_ADMIN = 1;
    public const STEP_QUOTE_READY = 2;

    protected $fillable = [
        'request_no',
        'order_ref',
        'case_id',
        'patient_name',
        'company_name',
        'request_date',
        'items_count',
        'doctor_name',
        'doctor_user_id',
        'patient_type',
        'status_key',
        'step',
        'approved_at',
        'approved_by',
        'approved_by_user_id',
    ];

    protected $casts = [
        'request_date' => 'date',
        'approved_at' => 'datetime',
        'items_count' => 'integer',
        'step' => 'integer',
        'status_key' => PricingRequestStatus::class,
    ];

    protected $appends = [
        'status_label',
    ];

    /**
     * تسمية الحالة للعرض فقط — تُشتق من status_key وليست مصدر حقيقة.
     */
    protected function statusLabel(): Attribute
    {
        return Attribute::get(
            fn (): string => $this->status_key instanceof PricingRequestStatus
                ? $this->status_key->label()
                : PricingRequestStatus::from((string) $this->status_key)->label()
        );
    }

    public function caseRecord(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_user_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PricingRequestItem::class);
    }

    /**
     * عرض السعر الناتج — علاقة 1:1 (QT-PENDING-xxx → QT-2026-xxx)
     */
    public function quote(): HasOne
    {
        return $this->hasOne(Quote::class);
    }
}
