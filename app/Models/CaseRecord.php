<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
    // مراحل المسار الرئيسية — التسلسل الصارم الجديد
    public const STAGE_RECEPTION = 'reception';
    public const STAGE_EXAM = 'exam';
    public const STAGE_TECHNICAL = 'technical';
    public const STAGE_ADJUSTMENTS = 'adjustments';
    public const STAGE_COST_CALC = 'cost_calc';
    public const STAGE_QUOTE = 'quote';
    public const STAGE_OPERATIONS = 'operations';
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
        'internal_cost',
        'military_selling_price',
        'military_markup_pct',
        'paid',
        'approval_date',
        'approval_confirmed_at',
        'ledger_posted_at',
        'delivered_at',
        'rank',
        'sovereign_entity',
        'credit_note_no',
        'credit_note_amount',
        'rework_reason',
        'rework_target',
        'rework_returned_at',
        'rework_returned_by',
    ];

    protected $casts = [
        'quote_date' => 'date',
        'quote_total' => 'decimal:2',
        'invoice_total' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'internal_cost' => 'decimal:2',
        'military_selling_price' => 'decimal:2',
        'military_markup_pct' => 'decimal:2',
        'paid' => 'decimal:2',
        'approval_date' => 'date',
        'approval_confirmed_at' => 'datetime',
        'ledger_posted_at'      => 'datetime',
        'delivered_at' => 'date',
        'credit_note_amount' => 'decimal:2',
        'rework_returned_at' => 'datetime',
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

    public function remainingAmount(): float
    {
        return max(0, (float) $this->total_cost - (float) $this->paid);
    }

    public function isMilitary(): bool
    {
        return $this->patient_type === Patient::TYPE_MILITARY;
    }

    /** الجهة المعروضة — مدني: جهة التعاقد، عسكري: القوات المسلحة. */
    public function displayEntity(): string
    {
        if ($this->isMilitary()) {
            return $this->sovereign_entity ?: Patient::MILITARY_SOVEREIGN_ENTITY;
        }

        return $this->company_name ?? '—';
    }

    protected static function booted(): void
    {
        static::saving(function (CaseRecord $case) {
            if ($case->patient_type !== Patient::TYPE_MILITARY) {
                return;
            }

            $case->sovereign_entity = $case->sovereign_entity ?: Patient::MILITARY_SOVEREIGN_ENTITY;
        });
    }

    /** حالات في مرحلة المعدلات — مراجعة وإضافة بنود قبل التسعير. */
    public function scopeInAdjustments(Builder $query): Builder
    {
        return $query->where('stage_key', self::STAGE_ADJUSTMENTS);
    }

    /** حالات في مرحلة التكاليف — بانتظار التأكيد اليدوي. */
    public function scopeInCostCalc(Builder $query): Builder
    {
        return $query->where('stage_key', self::STAGE_COST_CALC);
    }

    /** حالات وصلت لمكتب التشغيل (مركز القرار) — بانتظار الاعتماد أو الإعادة. */
    public function scopeAtOperations(Builder $query): Builder
    {
        return $query->where('stage_key', self::STAGE_OPERATIONS);
    }

    /** حالات دخلت الورشة فعلياً بعد صرف/تحويل BOM من المخزن. */
    public function scopeReleasedToWorkshop(Builder $query): Builder
    {
        return $query
            ->where('stage_key', self::STAGE_MANUFACTURING)
            ->whereHas('bom', fn (Builder $q) => $q->whereIn('stage', [Bom::STAGE_WIP, Bom::STAGE_FINISHED]));
    }

    /** حالات أُتمِم تصنيعها من مكتب التشغيل (BOM تام → جاهزة للتسليم أو مُسلَّمة). */
    public function scopeManufacturingCompletedByOps(Builder $query): Builder
    {
        return $query
            ->whereIn('stage_key', [self::STAGE_READY_DELIVERY, self::STAGE_DELIVERED])
            ->whereHas('bom', fn (Builder $q) => $q->where('stage', Bom::STAGE_FINISHED));
    }

    public static function countManufacturingCompletedByOps(): int
    {
        return static::query()->manufacturingCompletedByOps()->count();
    }

    public function isAtOperations(): bool
    {
        return $this->stage_key === self::STAGE_OPERATIONS;
    }

    public function isInAdjustments(): bool
    {
        return $this->stage_key === self::STAGE_ADJUSTMENTS;
    }

    public function isInCostCalc(): bool
    {
        return $this->stage_key === self::STAGE_COST_CALC;
    }

    /** ملاحظات التوصيف الفني — null إذا لم تُكتب أو فارغة. */
    public function resolvedTechNotes(): ?string
    {
        if (! $this->relationLoaded('techOrderSpec')) {
            return null;
        }

        $notes = trim((string) ($this->techOrderSpec?->tech_notes ?? ''));

        return $notes !== '' ? $notes : null;
    }

    public function clearReworkNotice(): void
    {
        $this->forceFill([
            'rework_reason'       => null,
            'rework_target'       => null,
            'rework_returned_at'  => null,
            'rework_returned_by'  => null,
        ])->save();
    }

    /** @return array{reason: string, target: string, target_label: string, returned_at: ?string, returned_by: ?string}|null */
    public function reworkNoticeFor(?string $stage = null): ?array
    {
        if ($stage !== null && $this->rework_target !== $stage) {
            return null;
        }

        if (! $this->rework_returned_at) {
            return null;
        }

        return [
            'reason'        => filled($this->rework_reason)
                ? $this->rework_reason
                : 'لم تُذكر ملاحظات من مكتب التشغيل.',
            'target'        => $this->rework_target,
            'target_label'  => match ($this->rework_target) {
                self::STAGE_ADJUSTMENTS => 'إرجاع من مكتب التشغيل — المعدلات الفنية',
                self::STAGE_TECHNICAL   => 'إرجاع من مكتب التشغيل — التوصيف الفني',
                default                 => 'إرجاع من مكتب التشغيل',
            },
            'returned_at'   => $this->rework_returned_at?->toIso8601String(),
            'returned_by'   => $this->rework_returned_by,
        ];
    }
}
