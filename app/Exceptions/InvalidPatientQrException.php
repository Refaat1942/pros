<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * مسح QR غير صالح أو لا يطابق جلسة التسليم — منع إغلاق الحالة.
 */
class InvalidPatientQrException extends RuntimeException
{
    public static function mismatch(): self
    {
        return new self('رمز QR لا يطابق بطاقة المريض — تم رفض التسليم.');
    }

    public static function tampered(): self
    {
        return new self('رمز QR مُعدَّل أو غير صالح — تم رفض التسليم لأسباب أمنية.');
    }

    public static function notReady(): self
    {
        return new self('الحالة ليست جاهزة للتسليم — QR مرفوض.');
    }

    public static function archived(): self
    {
        return new self('ملف المريض مُؤرشَف — QR منتهٍ.');
    }
}
