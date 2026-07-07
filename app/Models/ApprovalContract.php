<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

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
        'approval_date' => 'date',
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

    /**
     * القرص الذي يوجد عليه ملف الخطاب فعلياً.
     * الخطابات الجديدة تُخزَّن على القرص الخاص (local)؛ نُبقي fallback على public
     * لملفات قديمة رُفعت قبل التحويل. الوصول دائماً عبر مسار مُصادَق عليه.
     */
    public function letterDisk(): ?string
    {
        if (! $this->letter_path) {
            return null;
        }

        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($this->letter_path)) {
                return $disk;
            }
        }

        return null;
    }
}
