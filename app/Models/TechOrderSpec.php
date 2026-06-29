<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * التوصيف الفني المحفوظ — clinic_tech_order_specs
 */
class TechOrderSpec extends Model
{
    protected $fillable = [
        'order_ref',
        'case_id',
        'patient_name',
        'company_name',
        'doctor_name',
        'tech_notes',
        'submitted_at',
        'locked',
    ];

    protected $casts = [
        'submitted_at' => 'date',
        'locked'       => 'boolean',
    ];

    public function caseRecord(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class, 'case_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TechOrderSpecItem::class);
    }

    public function editRequests(): HasMany
    {
        return $this->hasMany(SpecEditRequest::class);
    }

    public function pendingEditRequest(): HasOne
    {
        return $this->hasOne(SpecEditRequest::class)
            ->where('status', \App\Enums\SpecEditRequestStatus::Pending);
    }
}
