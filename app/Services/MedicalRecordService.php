<?php

namespace App\Services;

use App\Enums\WorkflowEvent;
use App\Models\Appointment;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordItem;
use App\Models\Patient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * حفظ مسودات التقارير الطبية واعتمادها — يُطلق إنشاء الحالة التشغيلية.
 */
class MedicalRecordService
{
    public function __construct(
        private readonly CaseService $caseService,
    ) {
    }

    /**
     * حفظ مسودة تقرير طبي (إنشاء أو تحديث).
     */
    public function saveDraft(array $data): MedicalRecord
    {
        return DB::transaction(function () use ($data) {
            if (! empty($data['medical_record_id'])) {
                return $this->updateDraft($data);
            }

            return $this->createDraft($data);
        });
    }

    /**
     * اعتماد التقرير — قفل + إنشاء حالة + انتقال إلى التوصيف الفني.
     */
    public function lock(MedicalRecord $record): MedicalRecord
    {
        if ($record->locked) {
            abort(403, 'التقرير الطبي معتمد ولا يمكن تعديله.');
        }

        return DB::transaction(function () use ($record) {
            $before = $record->only(['locked', 'status', 'case_id']);

            $record->update([
                'locked' => true,
                'status' => MedicalRecord::STATUS_APPROVED,
            ]);

            if ($record->appointment_id) {
                Appointment::where('id', $record->appointment_id)->update([
                    'status'       => Appointment::STATUS_DONE,
                    'status_label' => 'منتهٍ',
                ]);
            }

            $patient = Patient::findOrFail($record->patient_id);
            $case    = $this->caseService->initiate($patient, $record);
            $this->caseService->advance($case, WorkflowEvent::ExamApproved->value);

            AuditService::log(
                action:      'lock',
                description: "اعتماد الكشف الطبي #{$record->id} — {$record->patient_name}",
                tag:         'medical',
                before:      $before,
                after:       $record->fresh()->only(['locked', 'status', 'case_id']),
            );

            return $record->fresh()->load(['items', 'patient', 'caseRecord']);
        });
    }

    private function createDraft(array $data): MedicalRecord
    {
        $patient     = Patient::findOrFail($data['patient_id']);
        $appointment = ! empty($data['appointment_id'])
            ? Appointment::findOrFail($data['appointment_id'])
            : null;

        if ($appointment) {
            $appointment->update([
                'status'                => Appointment::STATUS_IN_CLINIC,
                'status_label'          => 'في العيادة',
                'transferred_to_clinic' => true,
            ]);
        }

        $record = MedicalRecord::create([
            'patient_id'     => $patient->id,
            'appointment_id' => $appointment?->id,
            'patient_name'   => $patient->name,
            'national_id'    => $patient->national_id,
            'company_name'   => $patient->company_name,
            'patient_type'   => $patient->patient_type,
            'diagnosis'      => $data['diagnosis'],
            'prescription'   => $data['prescription'] ?? null,
            'doctor_name'    => Auth::user()->name,
            'doctor_user_id' => Auth::id(),
            'record_date'    => now()->toDateString(),
            'status'         => MedicalRecord::STATUS_DRAFT,
            'locked'         => false,
        ]);

        $this->syncItems($record, $data['items'] ?? []);

        AuditService::log(
            action:      'create',
            description: "مسودة تقرير طبي #{$record->id} — {$patient->name}",
            tag:         'medical',
            after:       ['id' => $record->id, 'patient_id' => $patient->id],
        );

        return $record->load('items');
    }

    private function updateDraft(array $data): MedicalRecord
    {
        $record = MedicalRecord::findOrFail($data['medical_record_id']);

        if ($record->locked) {
            abort(403, 'التقرير الطبي معتمد ولا يمكن تعديله.');
        }

        $before = $record->only(['diagnosis', 'prescription']);

        $record->update([
            'diagnosis'    => $data['diagnosis'],
            'prescription' => $data['prescription'] ?? null,
        ]);

        $this->syncItems($record, $data['items'] ?? []);

        AuditService::log(
            action:      'update',
            description: "تحديث مسودة تقرير طبي #{$record->id}",
            tag:         'medical',
            before:      $before,
            after:       $record->only(['diagnosis', 'prescription']),
        );

        return $record->fresh()->load('items');
    }

    /**
     * @param  list<array{stock_item_code: string, name: string, qty: int}>  $items
     */
    private function syncItems(MedicalRecord $record, array $items): void
    {
        $record->items()->delete();

        foreach ($items as $item) {
            MedicalRecordItem::create([
                'medical_record_id' => $record->id,
                'stock_item_code'   => $item['stock_item_code'],
                'name'              => $item['name'],
                'qty'               => $item['qty'],
            ]);
        }
    }
}
