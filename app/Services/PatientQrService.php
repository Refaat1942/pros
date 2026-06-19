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
     * مثال: 482917 → QR-482917
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

    /**
     * تحقق صارم لمسح التسليم — QR صالح + حالة ready_delivery + BOM تام.
     */
    public function assertValidForDelivery(string $qrString, \App\Models\CaseRecord $case, Patient $patient): void
    {
        if (! preg_match('/^QR-\d{6}$/', $qrString)) {
            throw \App\Exceptions\InvalidPatientQrException::tampered();
        }

        if ($patient->archived_at !== null) {
            throw \App\Exceptions\InvalidPatientQrException::archived();
        }

        if ($case->stage_key !== \App\Models\CaseRecord::STAGE_READY_DELIVERY) {
            throw \App\Exceptions\InvalidPatientQrException::notReady();
        }

        $case->loadMissing('bom');

        if (! $case->bom || $case->bom->stage !== \App\Models\Bom::STAGE_FINISHED) {
            throw \App\Exceptions\InvalidPatientQrException::notReady();
        }

        if (! $this->validate($qrString, $patient)) {
            throw \App\Exceptions\InvalidPatientQrException::mismatch();
        }
    }
}
