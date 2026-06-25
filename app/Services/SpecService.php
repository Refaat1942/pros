<?php

namespace App\Services;

use App\Enums\WorkflowEvent;
use App\Exceptions\InvalidSpecItemException;
use App\Models\CaseRecord;
use App\Models\MedicalRecord;
use App\Models\StockItem;
use App\Models\TechOrderSpec;
use App\Models\TechOrderSpecItem;
use Illuminate\Support\Facades\DB;

/**
 * حفظ وإرسال التوصيف الفني (الخطوة 3) — بنود وكميات فقط، بلا أسعار.
 * عند الإرسال يُنشئ BOM خام (source=spec) ويُحوّل الحالة إلى المعدلات (الخطوة 4).
 */
class SpecService
{
    public function __construct(
        private readonly WorkflowService $workflowService,
        private readonly BomService $bomService,
    ) {
    }

    /**
     * إعادة فتح التوصيف للتعديل بعد إرجاع الحالة من مكتب التشغيل (أو المعدلات).
     */
    public function reopenForRework(CaseRecord $case): void
    {
        if ($case->stage_key !== CaseRecord::STAGE_TECHNICAL) {
            return;
        }

        $spec = TechOrderSpec::where('case_id', $case->id)->first();

        if (! $spec?->locked) {
            return;
        }

        DB::transaction(function () use ($spec, $case) {
            $before = $spec->only(['locked', 'submitted_at']);

            $spec->update([
                'locked'       => false,
                'submitted_at' => null,
            ]);

            AuditService::log(
                action:      'reopen',
                description: "إعادة فتح التوصيف الفني للتعديل — {$case->case_no}",
                tag:         'spec',
                before:      $before,
                after:       $spec->only(['locked', 'submitted_at']),
            );
        });
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
     * إرسال التوصيف — يُنشئ BOM خام (بنود الفني) ويُحوّل الحالة إلى المعدلات.
     * لا تسعير هنا (عمى مالي للفني). المسار العسكري يمر بنفس مراحل المدني
     * (معدلات → تكاليف → تشغيل) باستثناء إصدار عرض السعر.
     */
    public function submit(TechOrderSpec $spec): CaseRecord
    {
        if ($spec->locked) {
            abort(422, 'تم إرسال التوصيف لهذه الحالة مسبقاً.');
        }

        $spec->load('items', 'caseRecord.patient');

        if ($spec->items->isEmpty()) {
            abort(422, 'يجب إضافة بند واحد على الأقل قبل الإرسال.');
        }

        $this->validateStockCodes($spec);

        $case = DB::transaction(function () use ($spec) {
            $case = CaseRecord::lockForUpdate()->findOrFail($spec->case_id);

            if ($case->stage_key !== CaseRecord::STAGE_TECHNICAL) {
                abort(422, 'الحالة ليست في مرحلة التوصيف الفني.');
            }

            $this->bomService->createSpecRaw($case, $spec->items->map(fn ($i) => [
                'stock_item_code' => $i->stock_item_code,
                'name'            => $i->name,
                'qty'             => $i->qty,
            ])->all());

            $spec->update([
                'locked'       => true,
                'submitted_at' => now(),
            ]);

            // التوصيف الفني → المعدلات
            $this->workflowService->advance($case, WorkflowEvent::SpecSaved->value);

            $case->clearReworkNotice();

            AuditService::log(
                action:      'submit',
                description: "إرسال التوصيف للمعدلات — {$case->case_no}",
                tag:         'spec',
                after:       ['case_id' => $case->id, 'items_count' => $spec->items->count()],
            );

            return $case->fresh();
        });

        return $case;
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
}
