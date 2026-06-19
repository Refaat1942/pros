<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * عدم تطابق بيانات OCR مع عرض السعر المجمّد.
 */
class OcrMismatchException extends RuntimeException
{
    public static function forField(string $field, string $detail = ''): self
    {
        $msg = "عدم تطابق OCR — {$field}";

        return new self($detail !== '' ? "{$msg}: {$detail}" : $msg);
    }
}
