<?php

namespace App\Support;

use App\Enums\CaseStage;
use App\Enums\ManufacturingStage;
use App\Enums\PricingRequestStatus;
use App\Models\CaseRecord;
use App\Models\PricingRequest;

/**
 * تسمية وحالة العرض الموحّدة للحالة التشغيلية — مصدرها CaseRecord.stage_key.
 */
final class CaseDisplayStatus
{
    public function __construct(
        public readonly string $label,
        public readonly string $badgeClass,
        public readonly string $filterKey,
        public readonly string $source = 'case',
    ) {}

    public static function forCase(?CaseRecord $case): self
    {
        if (! $case?->stage_key) {
            return new self('—', 'badge-secondary', 'unknown');
        }

        $label = CaseStage::labelFor($case->stage_key);

        if ($case->stage_key === CaseRecord::STAGE_MANUFACTURING && $case->manufacturing_stage) {
            $mfg = ManufacturingStage::labelFor($case->manufacturing_stage);
            if ($mfg !== '—') {
                $label .= ' — '.$mfg;
            }
        }

        return new self(
            label: $label,
            badgeClass: CaseStage::badgeClassFor($case->stage_key),
            filterKey: $case->stage_key,
            source: 'case',
        );
    }

    public static function forPricingRequest(PricingRequest $request): self
    {
        if ($request->relationLoaded('caseRecord') && $request->caseRecord) {
            return self::forCase($request->caseRecord);
        }

        if ($request->case_id) {
            $request->loadMissing('caseRecord:id,case_no,order_ref,stage_key,manufacturing_stage');

            if ($request->caseRecord) {
                return self::forCase($request->caseRecord);
            }
        }

        $status = $request->status_key instanceof PricingRequestStatus
            ? $request->status_key
            : PricingRequestStatus::from((string) $request->status_key);

        return new self(
            label: $status->label(),
            badgeClass: $status->badgeClass(),
            filterKey: $status->value,
            source: 'pricing',
        );
    }

    /** @return array{label: string, badge_class: string, filter_key: string, source: string} */
    public function toArray(): array
    {
        return [
            'label'       => $this->label,
            'badge_class' => $this->badgeClass,
            'filter_key'  => $this->filterKey,
            'source'      => $this->source,
        ];
    }
}
