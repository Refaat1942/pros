<?php

namespace App\Enums;

/**
 * تصنيف شجري المخزن — store_class في stock_items
 * يُوافق التصنيفات المذكورة في المواصفات: raw, wip, finished, molds, consumables, tools
 */
enum StockStoreClass: string
{
    case Raw = 'raw';
    case Wip = 'wip';
    case Finished = 'finished';
    case Molds = 'molds';
    case Consumables = 'consumables';
    case Tools = 'tools';

    public function label(): string
    {
        return match ($this) {
            self::Raw => 'خام',
            self::Wip => 'تشغيل',
            self::Finished => 'تام',
            self::Molds => 'قوالب',
            self::Consumables => 'مواد مساعدة',
            self::Tools => 'أدوات',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
