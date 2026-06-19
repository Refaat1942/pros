<?php

namespace App\Services;

use App\Models\ContractCompany;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;

/**
 * تسجيل وتحديث ملفات المرضى.
 */
class PatientService
{
    public function __construct(private readonly PatientQrService $patientQrService)
    {
    }

    /**
     * تسجيل مريض جديد — يُولِّد patient_code و patient_qr تلقائياً.
     */
    public function register(array $data): Patient
    {
        return DB::transaction(function () use ($data) {
            $type       = $data['patient_type'];
            $patientCode = $this->nextPatientCode($type);
            $patientQr   = $this->patientQrService->generate($patientCode);

            $companyName = null;
            if (! empty($data['contract_company_id'])) {
                $companyName = ContractCompany::where('id', $data['contract_company_id'])
                    ->value('name');
            }

            $patient = Patient::create([
                'patient_code'        => $patientCode,
                'patient_qr'          => $patientQr,
                'name'                => $data['name'],
                'phone'               => $data['phone'] ?? null,
                'national_id'         => $data['national_id'] ?? null,
                'patient_type'        => $type,
                'rank'                => $data['rank'] ?? null,
                'sovereign_entity'    => $data['sovereign_entity'] ?? null,
                'contract_company_id' => $data['contract_company_id'] ?? null,
                'company_name'        => $companyName,
                'registered_at'       => now()->toDateString(),
                'status'              => Patient::STATUS_ACTIVE,
            ]);

            AuditService::log(
                action:      'create',
                description: "تسجيل مريض جديد {$patient->patient_code} — {$patient->name}",
                tag:         'patients',
                after:       $this->auditSnapshot($patient),
            );

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
                action:      'update',
                description: "تعديل ملف مريض {$patient->patient_code}",
                tag:         'patients',
                before:      $before,
                after:       $this->auditSnapshot($patient->fresh()),
            );

            return $patient->fresh()->load('contractCompany');
        });
    }

    private function nextPatientCode(string $type): string
    {
        $prefix = $type === Patient::TYPE_MILITARY ? 'PT-MIL-' : 'PT-CIV-';

        $lastCode = Patient::where('patient_code', 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderByDesc('patient_code')
            ->value('patient_code');

        $num = $lastCode
            ? ((int) substr($lastCode, strlen($prefix)) + 1)
            : 1;

        return sprintf('%s%04d', $prefix, $num);
    }

    private function auditSnapshot(Patient $patient): array
    {
        return $patient->only([
            'patient_code',
            'patient_qr',
            'name',
            'phone',
            'national_id',
            'patient_type',
            'rank',
            'sovereign_entity',
            'contract_company_id',
            'company_name',
            'status',
        ]);
    }
}
