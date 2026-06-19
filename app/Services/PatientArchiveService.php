<?php

namespace App\Services;

use App\Models\Patient;

/**
 * أرشفة ملف المريض بعد إغلاق الحالة بالتسليم.
 */
class PatientArchiveService
{
    public function archiveOnDelivery(Patient $patient): Patient
    {
        if ($patient->archived_at !== null) {
            return $patient;
        }

        $before = [
            'status'      => $patient->status,
            'archived_at' => null,
        ];

        $patient->update([
            'status'      => Patient::STATUS_DONE,
            'archived_at' => now(),
            'last_visit_at' => now()->toDateString(),
        ]);

        AuditService::log(
            action:      'archive',
            description: "أرشفة ملف المريض — {$patient->patient_code}",
            tag:         'delivery',
            before:      $before,
            after:       [
                'status'      => Patient::STATUS_DONE,
                'archived_at' => now()->toIso8601String(),
            ],
        );

        return $patient->fresh();
    }
}
