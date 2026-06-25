<?php

namespace App\Services;

use App\Models\Patient;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * توليد QR لمتابعة الحالة — الرابط يُبنى ديناميكياً من tracking_uid.
 */
class CaseTrackingQrService
{
    public function __construct(private readonly TrackingUidService $trackingUidService)
    {
    }

    public function url(Patient $patient): string
    {
        return $this->trackingUidService->trackingUrl($patient->tracking_uid);
    }

    public function svg(Patient $patient, int $size = 200, int $margin = 1): string
    {
        return (string) QrCode::size($size)
            ->margin($margin)
            ->errorCorrection('H')
            ->generate($this->url($patient));
    }
}
