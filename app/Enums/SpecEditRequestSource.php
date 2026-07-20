<?php

namespace App\Enums;

enum SpecEditRequestSource: string
{
    case Spec = 'spec';
    case Adjustments = 'adjustments';
    case PostWorkOrder = 'post_work_order';

    public function label(): string
    {
        return match ($this) {
            self::Spec => 'توصيف فني',
            self::Adjustments => 'معدلات',
            self::PostWorkOrder => 'بعد أمر الشغل',
        };
    }
}
