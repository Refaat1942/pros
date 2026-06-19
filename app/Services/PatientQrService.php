<?php

namespace App\Services;

use App\Models\Patient;

/**
 * توليد والتحقق من رمز QR الخاص ببطاقة المريض.
 */
class PatientQrService
{
    /**
     * يُولِّد رمز QR فريداً من patient_code.
     * مثال: PT-CIV-0001 → QR-PT-CIV-0001
     */
    public function generate(string $patientCode): string
    {
        return 'QR-' . $patientCode;
    }

    /**
     * يتحقق أن الرمز الممسوح يخص المريض المحدد (Task 10 — مسح التسليم).
     */
    public function validate(string $qrString, Patient $patient): bool
    {
        return hash_equals($patient->patient_qr, $qrString);
    }
}
