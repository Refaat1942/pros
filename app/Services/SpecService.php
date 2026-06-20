<?php

namespace App\Services;

use App\Enums\PricingRequestStatus;
use App\Enums\WorkflowEvent;
use App\Exceptions\InvalidSpecItemException;
use App\Models\CaseRecord;
use App\Models\MedicalRecord;
use App\Models\PricingRequest;
use App\Models\PricingRequestItem;
use App\Models\StockItem;
use App\Models\TechOrderSpec;
use App\Models\TechOrderSpecItem;
use Illuminate\Support\Facades\DB;

/**
 * حفظ وإرسال التوصيف الفني — يُنشئ PricingRequest ويُحرِّك الـ workflow.
 */
class SpecService
{
    public function __construct(
        private readonly WorkflowService $workflowService,
        private readonly PricingService $pricingService,
        private readonly BomService $bomService,
    ) {
    }

    /**
     * إنشاء مسودة توصيف فني لحالة في مرحلة technical.
     */
    public function saveDraft(array $data): TechOrderSpec
    {
        return DB::transaction(function () use ($data) {
            $case = $this->resolveTechnicalCase($data['case_id']);

            if (TechOrderSpec::where('case_id', $case->id)->exists()) {
                abort(422, 'يوجد توصيف فني لهذه الحالة — استخدم التعديل بدلاً من الإنشاء.');
            }

            $doctorName = $this->resolveDoctorName($case);

            $spec = TechOrderSpec::create([
                'order_ref'    => $case->order_ref,
                'case_id'      => $case->id,
                'patient_name' => $case->patient->name,
                'company_name' => $case->company_name,
                'doctor_name'  => $doctorName,
                'tech_notes'   => $data['tech_notes'] ?? null,
                'locked'       => false,
            ]);

            $this->syncItems($spec, $data['items'] ?? []);

            AuditService::log(
                action:      'create',
                description: "مسودة توصيف فني #{$spec->id} — {$case->case_no}",
                tag:         'spec',
                after:       ['id' => $spec->id, 'case_id' => $case->id],
            );

            return $spec->load('items');
        });
    }

    /**
     * تحديث مسودة التوصيف (قبل الإرسال فقط).
     */
    public function updateDraft(TechOrderSpec $spec, array $data): TechOrderSpec
    {
        if ($spec->locked) {
            abort(403, 'التوصيف الفني مُرسَل ولا يمكن تعديله.');
        }

        return DB::transaction(function () use ($spec, $data) {
            $before = $spec->only(['tech_notes']);

            $spec->update([
                'tech_notes' => $data['tech_notes'] ?? $spec->tech_notes,
            ]);

            if (array_key_exists('items', $data)) {
                $this->syncItems($spec, $data['items']);
            }

            AuditService::log(
                action:      'update',
                description: "تحديث مسودة توصيف فني #{$spec->id}",
                tag:         'spec',
                before:      $before,
                after:       $spec->only(['tech_notes']),
            );

            return $spec->fresh()->load('items');
        });
    }

    /**
     * إرسال التوصيف للتسعير — يُنشئ PricingRequest ويُحرِّك الحالة إلى cost_calc.
     */
    public function submit(TechOrderSpec $spec): PricingRequest
    {
        if ($spec->locked) {
            abort(422, 'تم إرسال التوصيف لهذه الحالة مسبقاً.');
        }

        $spec->load('items', 'caseRecord.patient');

        if ($spec->items->isEmpty()) {
            abort(422, 'يجب إضافة بند واحد على الأقل قبل الإرسال.');
        }

        $this->validateStockCodes($spec);

        return DB::transaction(function () use ($spec) {
            $case = CaseRecord::lockForUpdate()->findOrFail($spec->case_id);

            if ($case->pricing_request_id) {
                abort(422, 'تم إرسال التوصيف لهذه الحالة مسبقاً.');
            }

            $requestNo = $this->nextRequestNo();
            $doctor    = MedicalRecord::where('case_id', $case->id)
                ->where('locked', true)
                ->latest()
                ->first();

            $pricingRequest = PricingRequest::create([
                'request_no'        => $requestNo,
                'order_ref'         => $case->order_ref,
                'case_id'           => $case->id,
                'patient_name'      => $spec->patient_name,
                'company_name'      => $spec->company_name,
                'request_date'      => now()->toDateString(),
                'items_count'       => $spec->items->count(),
                'doctor_name'       => $spec->doctor_name,
                'doctor_user_id'    => $doctor?->doctor_user_id,
                'patient_type'      => $case->patient_type,
                'status_key'        => PricingRequestStatus::Processing->value,
                'step'              => PricingRequest::STEP_ADMIN,
            ]);

            foreach ($spec->items as $item) {
                PricingRequestItem::create([
                    'pricing_request_id' => $pricingRequest->id,
                    'stock_item_code'    => $item->stock_item_code,
                    'name'               => $item->name,
                    'qty'                => $item->qty,
                ]);
            }

            $pricingRequest->load('items');
            $this->pricingService->calculate($pricingRequest);

            $spec->update([
                'locked'       => true,
                'submitted_at' => now(),
            ]);

            $case->update(['pricing_request_id' => $pricingRequest->id]);

            $this->workflowService->advance($case, WorkflowEvent::SpecSaved->value);

            $this->bomService->createSpecRaw($case->fresh(), $spec->items->map(fn ($i) => [
                'stock_item_code' => $i->stock_item_code,
                'name'            => $i->name,
                'qty'             => $i->qty,
            ])->all());

            // المسار العسكري: اعتماد تلقائي فوري — تجاوز طابور موافقة الإدارة بالكامل.
            // تُولَّد أمر الشغل (WO-*) وتنتقل الحالة مباشرةً إلى manufacturing/warehouse.
            if ($case->isMilitary()) {
                $this->pricingService->silentAutoApprove($pricingRequest->fresh());
            }

            AuditService::log(
                action:      'create',
                description: "إرسال التوصيف للتسعير — {$requestNo} — {$case->case_no}",
                tag:         'spec',
                after:       $pricingRequest->only([
                    'id', 'request_no', 'case_id', 'items_count', 'status_key',
                ]),
            );

            return $pricingRequest->load('items');
        });
    }

    /**
     * @param  list<array{stock_item_code: string, name: string, qty: int}>  $items
     */
    private function syncItems(TechOrderSpec $spec, array $items): void
    {
        $spec->items()->delete();

        foreach ($items as $item) {
            TechOrderSpecItem::create([
                'tech_order_spec_id' => $spec->id,
                'stock_item_code'    => $item['stock_item_code'],
                'name'               => $item['name'],
                'qty'                => $item['qty'],
            ]);
        }
    }

    private function validateStockCodes(TechOrderSpec $spec): void
    {
        $codes = $spec->items->pluck('stock_item_code')->filter()->unique();

        $existing = StockItem::whereIn('code', $codes)->pluck('code');

        foreach ($codes as $code) {
            if (! $existing->contains($code)) {
                throw new InvalidSpecItemException($code);
            }
        }
    }

    private function resolveTechnicalCase(int $caseId): CaseRecord
    {
        $case = CaseRecord::with('patient')->findOrFail($caseId);

        if ($case->stage_key !== CaseRecord::STAGE_TECHNICAL) {
            abort(422, 'الحالة ليست في مرحلة التوصيف الفني.');
        }

        return $case;
    }

    private function resolveDoctorName(CaseRecord $case): ?string
    {
        return MedicalRecord::where('case_id', $case->id)
            ->where('locked', true)
            ->latest()
            ->value('doctor_name');
    }

    private function nextRequestNo(): string
    {
        do {
            $requestNo = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
        } while (PricingRequest::where('request_no', $requestNo)->lockForUpdate()->exists());

        return $requestNo;
    }
}
