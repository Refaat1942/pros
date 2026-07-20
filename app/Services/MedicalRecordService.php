<?php

namespace App\Services;

use App\Enums\WorkflowEvent;
use App\Models\Appointment;
use App\Models\CaseRecommendation;
use App\Models\CaseRecord;
use App\Models\MedicalRecord;
use App\Models\MedicalRecordItem;
use App\Models\Patient;
use App\Services\Dashboard\DashboardQueueService;
use App\Services\Notifications\NotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * حفظ مسودات التقارير الطبية واعتمادها — يُطلق إنشاء الحالة التشغيلية.
 */
class MedicalRecordService
{
    public function __construct(
        private readonly CaseService $caseService,
        private readonly DashboardQueueService $queueService,
        private readonly NotificationService $notifications,
        private readonly CaseWorkflowSkipService $workflowSkip,
    ) {}

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
            return $record->fresh()->load(['items', 'patient', 'caseRecord']);
        }

        return DB::transaction(function () use ($record) {
            $before = $record->only(['locked', 'status', 'case_id']);

            $record->update([
                'locked' => true,
                'status' => MedicalRecord::STATUS_APPROVED,
            ]);

            if ($record->appointment_id) {
                Appointment::where('id', $record->appointment_id)->update([
                    'status' => Appointment::STATUS_DONE,
                    'status_label' => 'منتهٍ',
                ]);
            }

            $patient = Patient::findOrFail($record->patient_id);

            if ($record->appointment_id) {
                $alreadyLocked = MedicalRecord::where('appointment_id', $record->appointment_id)
                    ->where('locked', true)
                    ->where('id', '!=', $record->id)
                    ->exists();

                if ($alreadyLocked) {
                    abort(422, 'تم اعتماد تقرير لهذا الموعد مسبقاً.');
                }
            }

            if ($record->case_id) {
                $case = CaseRecord::findOrFail($record->case_id);
                if (in_array($case->stage_key, [CaseRecord::STAGE_RECEPTION, CaseRecord::STAGE_EXAM], true)) {
                    $this->caseService->advance($case, WorkflowEvent::ExamApproved->value);
                }
            } else {
                $case = $this->caseService->initiate($patient, $record);
                $this->caseService->advance($case, WorkflowEvent::ExamApproved->value);
            }

            AuditService::log(
                action: 'lock',
                description: "اعتماد الكشف الطبي #{$record->id} — {$record->patient_name}",
                tag: 'medical',
                before: $before,
                after: $record->fresh()->only(['locked', 'status', 'case_id']),
            );

            $record = $record->fresh()->load(['items', 'patient', 'caseRecord']);
            if ($record->caseRecord) {
                $this->syncCaseRecommendations($record->caseRecord, $record);
            }

            $this->maybeNotifyReceptionIfClinicQueueEmpty($record->appointment_id);

            return $record;
        });
    }

    /**
     * تخطّي الكشف الطبي (الكشف اختياري) — دفع الحالة مباشرةً للتوصيف.
     *
     * يُستخدم عندما يكون الطبيب مستعجلاً: لا يُنشأ تقرير طبي، تُنشأ الحالة
     * من الاستقبال وتقفز للتوصيف، ويُغلق الموعد.
     */
    public function skipExam(Appointment $appointment): CaseRecord
    {
        $lockedExists = MedicalRecord::where('appointment_id', $appointment->id)
            ->where('locked', true)
            ->exists();

        abort_if($lockedExists, 422, 'تم اعتماد تقرير لهذا الموعد مسبقاً — لا يمكن التخطّي.');

        $user = Auth::user();
        abort_unless($user, 403);

        $case = $this->workflowSkip->skipExamForAppointment($appointment, $user);

        $this->maybeNotifyReceptionIfClinicQueueEmpty($appointment->id);

        return $case;
    }

    private function createDraft(array $data): MedicalRecord
    {
        $patient = Patient::findOrFail($data['patient_id']);
        $appointment = ! empty($data['appointment_id'])
            ? Appointment::findOrFail($data['appointment_id'])
            : null;

        if ($appointment) {
            if ($appointment->status !== Appointment::STATUS_IN_CLINIC || ! $appointment->transferred_to_clinic) {
                abort(422, 'يجب تحويل المريض من الاستقبال قبل بدء الكشف.');
            }

            $existing = MedicalRecord::where('appointment_id', $appointment->id)->first();

            if ($existing) {
                if ($existing->locked) {
                    abort(422, 'تم اعتماد تقرير لهذا الموعد مسبقاً.');
                }

                return $this->updateDraft(array_merge($data, [
                    'medical_record_id' => $existing->id,
                ]));
            }
        }

        $record = MedicalRecord::create([
            'patient_id' => $patient->id,
            'appointment_id' => $appointment?->id,
            'patient_name' => $patient->name,
            'national_id' => $patient->national_id,
            'company_name' => $patient->company_name,
            'patient_type' => $patient->patient_type,
            'diagnosis' => $data['diagnosis'],
            'prescription' => $data['prescription'] ?? null,
            'doctor_name' => Auth::user()->name,
            'doctor_user_id' => Auth::id(),
            'record_date' => now()->toDateString(),
            'status' => MedicalRecord::STATUS_DRAFT,
            'locked' => false,
        ]);

        $this->syncItems($record, $data['items'] ?? []);

        AuditService::log(
            action: 'create',
            description: "مسودة تقرير طبي #{$record->id} — {$patient->name}",
            tag: 'medical',
            after: ['id' => $record->id, 'patient_id' => $patient->id],
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
            'diagnosis' => $data['diagnosis'],
            'prescription' => $data['prescription'] ?? null,
        ]);

        $this->syncItems($record, $data['items'] ?? []);

        AuditService::log(
            action: 'update',
            description: "تحديث مسودة تقرير طبي #{$record->id}",
            tag: 'medical',
            before: $before,
            after: $record->only(['diagnosis', 'prescription']),
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
                'stock_item_code' => $item['stock_item_code'],
                'name' => $item['name'],
                'qty' => $item['qty'],
            ]);
        }
    }

    private function syncCaseRecommendations(CaseRecord $case, MedicalRecord $record): void
    {
        if ($record->items->isEmpty()) {
            return;
        }

        $case->recommendations()->delete();

        foreach ($record->items as $item) {
            CaseRecommendation::create([
                'case_id' => $case->id,
                'stock_item_code' => $item->stock_item_code,
                'name' => $item->name,
                'qty' => $item->qty,
            ]);
        }
    }

    private function maybeNotifyReceptionIfClinicQueueEmpty(?int $appointmentId): void
    {
        if (! $appointmentId) {
            return;
        }

        $appointment = Appointment::find($appointmentId);

        if (! $appointment) {
            return;
        }

        $date = $appointment->appointment_date?->toDateString()
            ?? now()->toDateString();

        if ($this->queueService->doctorWaitingCount($date) === 0) {
            $this->notifications->notifyReceptionClinicQueueEmpty($date);
        }
    }
}
