<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ContractCompany;
use App\Models\MilitaryRank;
use App\Models\Patient;
use App\Support\ClinicTime;
use Illuminate\Support\Facades\DB;

/**
 * تسجيل وتحديث ملفات المرضى.
 */
class PatientService
{
    public function __construct(
        private readonly PatientQrService $patientQrService,
        private readonly AppointmentService $appointmentService,
        private readonly TrackingUidService $trackingUidService,
    ) {}

    /**
     * تسجيل مريض جديد — يُولِّد patient_code و patient_qr تلقائياً.
     */
    public function register(array $data): Patient
    {
        return DB::transaction(function () use ($data) {
            $type = $data['patient_type'];
            $patientCode = $this->nextPatientCode($type);
            $patientSerial = $this->nextPatientSerial();
            $patientQr = $this->patientQrService->generate($patientCode);

            $companyName = null;
            $contractCompanyId = null;
            $sovereignEntity = null;

            if ($type === Patient::TYPE_MILITARY) {
                $sovereignEntity = Patient::MILITARY_SOVEREIGN_ENTITY;
            } else {
                $contractCompanyId = $data['contract_company_id'] ?? null;
                if ($contractCompanyId) {
                    $companyName = ContractCompany::where('id', $contractCompanyId)
                        ->value('name');
                }
            }

            // Resolve rank name from FK for denormalized display field
            $rankName = null;
            if (! empty($data['military_rank_id'])) {
                $rankName = MilitaryRank::where('id', $data['military_rank_id'])->value('name');
            }

            $patient = Patient::create([
                'patient_code' => $patientCode,
                'patient_serial' => $patientSerial,
                'patient_qr' => $patientQr,
                'tracking_uid' => $this->trackingUidService->generate(),
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'national_id' => $data['national_id'] ?? null,
                'patient_type' => $type,
                'military_rank_id' => $data['military_rank_id'] ?? null,
                'military_number' => $data['military_number'] ?? null,
                'seniority_number' => $data['seniority_number'] ?? null,
                'military_weapon' => $data['military_weapon'] ?? null,
                'rank' => $rankName,
                'sovereign_entity' => $sovereignEntity,
                'contract_company_id' => $contractCompanyId,
                'company_name' => $companyName,
                'registered_at' => ClinicTime::todayDateString(),
                'status' => Patient::STATUS_ACTIVE,
            ]);

            AuditService::log(
                action: 'create',
                description: "تسجيل مريض جديد {$patient->patient_code} — {$patient->name}",
                tag: 'patients',
                after: $this->auditSnapshot($patient),
            );

            $clinicDay = ClinicTime::clinicDayDateString();
            $nextQueue = (int) Appointment::query()
                ->whereDate('clinic_day', $clinicDay)
                ->lockForUpdate()
                ->max('queue_number');

            $this->appointmentService->book([
                'patient_id' => $patient->id,
                // تاريخ العرض بالتقويم الفعلي (توقيت المركز) ليظهر المريض في طابور اليوم؛
                // بينما clinic_day يتبع يوم العمل (يبدأ 01:00) لترقيم الدور فقط.
                'appointment_date' => ClinicTime::todayDateString(),
                'visit_type_id' => $data['visit_type_id'],
                'clinic_day' => $clinicDay,
                'queue_number' => $nextQueue + 1,
            ]);

            return $patient->load('contractCompany');
        });
    }

    /**
     * تحديث الحقول غير الثابتة (الهاتف، جهة التعاقد).
     * patient_code و patient_qr لا يُعدَّلان أبداً.
     */
    public function update(Patient $patient, array $data): Patient
    {
        return DB::transaction(function () use ($patient, $data) {
            $before = $this->auditSnapshot($patient);

            $updates = array_intersect_key($data, array_flip(['phone', 'contract_company_id']));

            if (array_key_exists('contract_company_id', $updates)) {
                $updates['company_name'] = $updates['contract_company_id']
                    ? ContractCompany::where('id', $updates['contract_company_id'])->value('name')
                    : null;
            }

            $patient->update($updates);

            AuditService::log(
                action: 'update',
                description: "تعديل ملف مريض {$patient->patient_code}",
                tag: 'patients',
                before: $before,
                after: $this->auditSnapshot($patient->fresh()),
            );

            return $patient->fresh()->load('contractCompany');
        });
    }

    private function nextPatientCode(string $type): string
    {
        unset($type);

        do {
            $code = (string) random_int(100000, 999999);
        } while (Patient::where('patient_code', $code)->exists());

        return $code;
    }

    /**
     * سيريال تسلسلي سنوي للمريض بصيغة PT-YYYY-NNNN.
     * قفل صفّي على آخر سيريال للسنة لمنع التكرار عند التسجيل المتزامن.
     */
    private function nextPatientSerial(): string
    {
        $year = now()->year;
        $prefix = "PT-{$year}-";

        $last = Patient::where('patient_serial', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('patient_serial')
            ->value('patient_serial');

        $num = $last
            ? ((int) substr($last, strlen($prefix)) + 1)
            : 1;

        do {
            $serial = sprintf('%s%04d', $prefix, $num++);
        } while (Patient::where('patient_serial', $serial)->exists());

        return $serial;
    }

    private function auditSnapshot(Patient $patient): array
    {
        return $patient->only([
            'patient_code',
            'patient_serial',
            'patient_qr',
            'tracking_uid',
            'name',
            'phone',
            'national_id',
            'patient_type',
            'military_rank_id',
            'military_number',
            'seniority_number',
            'military_weapon',
            'rank',
            'sovereign_entity',
            'contract_company_id',
            'company_name',
            'status',
        ]);
    }
}
