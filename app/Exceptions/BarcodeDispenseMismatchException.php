<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * يُرمى عند مسح باركود لا يطابق بنود BOM — لا تغيير في المخزون.
 */
class BarcodeDispenseMismatchException extends RuntimeException
{
    public static function forItem(string $stockItemCode): self
    {
        return new self("باركود غير مطابق للصنف المتوقع: {$stockItemCode}");
    }
}
