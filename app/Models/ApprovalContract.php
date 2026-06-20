<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * أرشيف عقود الاعتماد المالي — مدني فقط.
 * يُنشأ تلقائياً عند إتمام مطابقة OCR وتوليد أمر الشغل.
 */
class ApprovalContract extends Model
{
    protected $fillable = [
        'contract_no',
        'case_id',
        'quote_id',
        'patient_name',
        'company_name',
        'approved_amount',
        'approval_date',
        'work_order_no',
        'letter_path',
        'letter_ref',
        'letter_date',
    ];

    protected $casts = [
        'approval_date'   => 'date',
        'approved_amount' => 'decimal:2',
    ];

    public function caseRecord(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}
