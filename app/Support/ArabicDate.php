<?php

namespace App\Support;

use Carbon\CarbonInterface;

final class ArabicDate
{
    public static function relative(?CarbonInterface $at): string
    {
        if (! $at) {
            return '—';
        }

        $zone = ClinicTime::zone();
        $localized = $at->copy()->timezone($zone)->locale('ar');
        $now = now()->timezone($zone);

        return $localized->diffForHumans($now)
            .' · '
            .$localized->translatedFormat('j F Y، g:i a');
    }
}
