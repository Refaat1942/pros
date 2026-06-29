<?php

namespace App\Enums;

/**
 * وحدة القياس — uom في stock_items
 */
enum StockUom: string
{
    case Piece  = 'قطعة';
    case Meter  = 'متر';
    case Gram   = 'جرام';
    case Liter  = 'لتر';
    case Set    = 'طقم';
    case Roll   = 'لفة';
    case Kilo   = 'كيلو';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
