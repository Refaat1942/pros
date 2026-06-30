<?php

namespace App\Enums;

/**
 * مراحل التصنيع الفرعية — manufacturing_stage على CaseRecord.
 */
enum ManufacturingStage: string
{
    case Warehouse  = 'warehouse';
    case Issue      = 'issue';
    case Generation = 'generation';
    case Assembly   = 'assembly';
    case Casting    = 'casting';
    case Finishing  = 'finishing';
    case Workshop   = 'workshop';
    case Fitting    = 'fitting';
    case Quality    = 'quality';
    case Closed     = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Warehouse  => 'المخزن',
            self::Issue      => 'صرف خامات',
            self::Generation => 'توليد',
            self::Assembly   => 'تجميع',
            self::Casting    => 'صب',
            self::Finishing  => 'تشطيب',
            self::Workshop   => 'الورشة',
            self::Fitting    => 'قياس',
            self::Quality    => 'جودة',
            self::Closed     => 'مغلق',
        };
    }

    public static function labelFor(?string $key): string
    {
        if (! $key) {
            return '—';
        }

        return self::tryFrom($key)?->label() ?? $key;
    }

    /** تسميات مرحلة التصنيع في طابور الورشة. */
    public static function workshopDeskLabelFor(?string $key): string
    {
        return match ($key) {
            self::Issue->value    => 'قيد التصنيع',
            self::Assembly->value => 'تم التصنيع',
            default               => self::labelFor($key),
        };
    }
}
