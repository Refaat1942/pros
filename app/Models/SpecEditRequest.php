<?php

namespace App\Models;

use App\Enums\SpecEditRequestSource;
use App\Enums\SpecEditRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * طلب تعديل توصيف فني مُرسَل — يتطلب موافقة الإدارة.
 */
class SpecEditRequest extends Model
{
    protected $fillable = [
        'source',
        'tech_order_spec_id',
        'case_id',
        'requested_by_user_id',
        'status',
        'original_items',
        'proposed_items',
        'original_tech_notes',
        'proposed_tech_notes',
        'rejection_reason_key',
        'rejection_notes',
        'reviewed_by_user_id',
        'reviewed_at',
    ];

    protected $casts = [
        'source' => SpecEditRequestSource::class,
        'status' => SpecEditRequestStatus::class,
        'original_items' => 'array',
        'proposed_items' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function techOrderSpec(): BelongsTo
    {
        return $this->belongsTo(TechOrderSpec::class);
    }

    public function caseRecord(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === SpecEditRequestStatus::Pending;
    }

    public function rejectionReasonLabel(): ?string
    {
        if (! $this->rejection_reason_key) {
            return null;
        }

        return config('spec_edit.rejection_reasons.'.$this->rejection_reason_key)
            ?? $this->rejection_reason_key;
    }
}
