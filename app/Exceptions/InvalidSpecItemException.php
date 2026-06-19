<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * يُرمى عند وجود كود صنف غير مسجَّل في stock_items داخل التوصيف الفني.
 */
class InvalidSpecItemException extends RuntimeException
{
    public function __construct(public readonly string $stockItemCode)
    {
        parent::__construct("كود الصنف غير موجود: {$stockItemCode}");
    }
}
