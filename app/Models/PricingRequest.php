<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * طلب التسعير — clinic_pricing_queue
 */
class PricingRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';

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
    ];

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

    public function quote(): HasMany
    {
        return $this->hasMany(Quote::class);
    }
}
