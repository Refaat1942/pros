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

        $localized = $at->copy()->locale('ar');

        return $localized->diffForHumans()
            .' · '
            .$localized->translatedFormat('j F Y، g:i a');
    }
}
