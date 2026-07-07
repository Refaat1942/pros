<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * دفعة نقدية مُحصَّلة من الخزنة — مسار الكاش (المريض على نفقته الشخصية).
 *
 * المعرف الفريد: payment_no — صيغة PAY-{سنة}-{تسلسل}
 */
class Payment extends Model
{
    protected $fillable = [
        'payment_no',
        'case_id',
        'quote_id',
        'patient_id',
        'patient_name',
        'amount',
        'method',
        'reference',
        'received_by',
        'received_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'received_at' => 'datetime',
    ];

    public function caseRecord(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function methodLabel(): string
    {
        return PaymentMethod::labelFor($this->method);
    }
}
