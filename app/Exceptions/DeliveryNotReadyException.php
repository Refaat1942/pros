<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * يُرمى عند محاولة تسليم حالة غير جاهزة (BOM غير مكتمل أو مرحلة خاطئة).
 */
class DeliveryNotReadyException extends RuntimeException
{
    public static function withReason(string $reason): self
    {
        return new self($reason);
    }
}
