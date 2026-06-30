<?php

namespace App\Enums;

enum SpecEditRequestSource: string
{
    case Spec = 'spec';
    case Adjustments = 'adjustments';

    public function label(): string
    {
        return match ($this) {
            self::Spec         => 'توصيف فني',
            self::Adjustments  => 'معدلات',
        };
    }
}
