<?php

namespace App\Services;

use App\Models\CaseRecord;
use App\Models\Patient;
use Illuminate\Support\Str;

/**
 * مفتاح متابعة عام — يُولَّد عند تسجيل المريض ويُنسَخ للحالة عند إنشائها.
 */
class TrackingUidService
{
    public function generate(): string
    {
        do {
            $uid = 'case-'.Str::lower(Str::random(8));
        } while (
            Patient::where('tracking_uid', $uid)->exists()
            || CaseRecord::where('tracking_uid', $uid)->exists()
        );

        return $uid;
    }

    public function trackingUrl(string $trackingUid): string
    {
        return route('public.track.case', ['uid' => $trackingUid]);
    }
}
