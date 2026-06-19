<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * الحالة التشغيلية — Aggregate Root: clinic_cases_workflow
 * (اسم الموديل CaseRecord لأن Case محجوز في PHP)
 */
class CaseRecord extends Model
{
    // مراحل المسار الرئيسية — STAGES في cases-workflow.js
    public const STAGE_RECEPTION = 'reception';
    public const STAGE_EXAM = 'exam';
    public const STAGE_TECHNICAL = 'technical';
    public const STAGE_COST_CALC = 'cost_calc';
    public const STAGE_ADMIN_APPROVAL = 'admin_approval';
    public const STAGE_QUOTE = 'quote';
    public const STAGE_WAITING_RETURN = 'waiting_return';
    public const STAGE_MANUFACTURING = 'manufacturing';
    public const STAGE_READY_DELIVERY = 'ready_delivery';
    public const STAGE_DELIVERED = 'delivered';

    // مراحل التصنيع الفرعية — MANUFACTURING_STAGES
    public const MFG_WAREHOUSE = 'warehouse';
    public const MFG_WORKSHOP = 'workshop';
    public const MFG_FITTING = 'fitting';
    public const MFG_QUALITY = 'quality';
    public const MFG_ISSUE = 'issue';
    public const MFG_GENERATION = 'generation';
    public const MFG_ASSEMBLY = 'assembly';
    public const MFG_CASTING = 'casting';
    public const MFG_FINISHING = 'finishing';
    public const MFG_CLOSED = 'closed';

    public const PATH_STANDARD = 'standard';
    public const PATH_MILITARY = 'military';
    public const PATH_OCR_BYPASS = 'ocr_bypass';

    protected $table = 'cases';

    protected $fillable = [
        'case_no',
        'order_ref',
        'tracking_uid',
        'patient_id',
        'contract_company_id',
        'company_name',
        'patient_type',
        'path',
        'stage_key',
        'manufacturing_stage',
        'work_order_no',
        'pricing_request_id',
        'quote_no',
        'quote_date',
        'quote_total',
        'invoice_no',
        'invoice_total',
        'total_cost',
        'paid',
        'approval_date',
        'approval_confirmed_at',
        'ledger_posted_at',
        'delivered_at',
        'rank',
        'sovereign_entity',
        'credit_note_no',
        'credit_note_amount',
    ];

    protected $casts = [
        'quote_date' => 'date',
        'quote_total' => 'decimal:2',
        'invoice_total' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'paid' => 'decimal:2',
        'approval_date' => 'date',
        'approval_confirmed_at' => 'datetime',
        'ledger_posted_at'      => 'datetime',
        'delivered_at' => 'date',
        'credit_note_amount' => 'decimal:2',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function contractCompany(): BelongsTo
    {
        return $this->belongsTo(ContractCompany::class);
    }

    public function pricingRequest(): BelongsTo
    {
        return $this->belongsTo(PricingRequest::class);
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(CaseRecommendation::class, 'case_id');
    }

    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class, 'case_id');
    }

    public function techOrderSpec(): HasOne
    {
        return $this->hasOne(TechOrderSpec::class, 'case_id');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class, 'case_id');
    }

    public function bom(): HasOne
    {
        return $this->hasOne(Bom::class, 'case_id');
    }

    public function returnNotes(): HasMany
    {
        return $this->hasMany(ReturnNote::class, 'case_id');
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class, 'case_id');
    }

    public function fittingTrial(): HasOne
    {
        return $this->hasOne(FittingTrial::class, 'case_id');
    }

    public function remainingAmount(): float
    {
        return max(0, (float) $this->total_cost - (float) $this->paid);
    }

    public function isMilitary(): bool
    {
        return $this->patient_type === Patient::TYPE_MILITARY;
    }
}
