<?php

namespace App\Enums;

enum StockCategoryFieldType: string
{
    case Text = 'text';
    case Number = 'number';
    case List = 'list';
    case Radio = 'radio';
    case Checkbox = 'checkbox';
    case Color = 'color';
    case Range = 'range';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Text => 'نص',
            self::Number => 'رقم',
            self::List => 'قائمة',
            self::Radio => 'اختيار واحد',
            self::Checkbox => 'خانات اختيار',
            self::Color => 'لون',
            self::Range => 'شريط',
        };
    }
}
