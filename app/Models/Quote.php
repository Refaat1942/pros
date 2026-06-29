<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * عرض السعر الرسمي — quotations في reception-dashboard.js
 *
 * المعرف الفريد: quote_no (سريال عرض السعر) — صيغة QT-{سنة}-{تسلسل}
 */
class Quote extends Model
{
    /** تسمية الحقل الفريد في الواجهة */
    public const SERIAL_LABEL = 'سريال عرض السعر';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_ISSUED = 'issued';

    protected $fillable = [
        'quote_no',
        'order_ref',
        'case_id',
        'pricing_request_id',
        'patient_name',
        'company_name',
        'quote_date',
        'status',
        'status_label',
        'total',
    ];

    protected $casts = [
        'quote_date' => 'date',
        'total' => 'decimal:2',
    ];

    protected $appends = [
        'quote_serial',
    ];

    /** سريال عرض السعر — نفس quote_no (المعرف الفريد) */
    public function getQuoteSerialAttribute(): string
    {
        return (string) $this->quote_no;
    }

    public function caseRecord(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }

    public function pricingRequest(): BelongsTo
    {
        return $this->belongsTo(PricingRequest::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }
}
