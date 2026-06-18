<?php

namespace Database\Seeders;

use App\Models\Patient;
use Database\Seeders\Support\PrototypeSeedData;
use Database\Seeders\Support\SeedRegistry;
use Illuminate\Database\Seeder;

class PatientSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PrototypeSeedData::cases() as $case) {
            $patientType = PrototypeSeedData::derivePatientType($case);
            $patientCode = PrototypeSeedData::derivePatientId($case['id'], $patientType);

            if (isset(SeedRegistry::$patients[$patientCode])) {
                continue;
            }

            $companyId = SeedRegistry::$companies[$case['company']] ?? null;

            $patient = Patient::query()->create([
                'patient_code' => $patientCode,
                'patient_qr' => 'QR-'.$patientCode,
                'name' => $case['patient'],
                'patient_type' => $patientType,
                'rank' => $case['rank'] ?? ($patientType === 'military' ? 'غير محدد' : null),
                'sovereign_entity' => $case['sovereignEntity'] ?? null,
                'contract_company_id' => $companyId,
                'company_name' => $case['company'],
                'registered_at' => PrototypeSeedData::parseDate($case['createdAt']),
                'last_visit_at' => PrototypeSeedData::parseDate($case['createdAt']),
                'status' => $case['stageKey'] === 'delivered' ? 'done' : 'active',
            ]);

            SeedRegistry::$patients[$patientCode] = $patient->id;
        }
    }
}
