<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * رقم عشوائي بستة أرقام لا يتكرر بين رقم المريض ورقم الطلب ورقم طلب التسعير.
 *
 * الحجز بإدراج في جدول reserved_numbers ذي المفتاح الفريد: إن سبق جهاز آخر لنفس الرقم
 * يفشل الإدراج بصمت (insertOrIgnore = 0) فيُجرَّب رقم آخر — لا فحص ثم إدراج قابل للسباق.
 */
final class ReservedNumber
{
    public const KIND_PATIENT = 'patient';

    public const KIND_ORDER_REF = 'order_ref';

    public const KIND_PRICING_REQUEST = 'pricing_request';

    /**
     * @param  callable(): string  $candidate  يولّد رقماً مرشحاً
     * @param  (callable(string): bool)|null  $takenElsewhere  فحص إضافي في جداول النوع (للبيانات القديمة)
     */
    public static function claim(string $kind, callable $candidate, ?callable $takenElsewhere = null): string
    {
        for ($attempt = 0; $attempt < 1000; $attempt++) {
            $number = (string) $candidate();

            if ($takenElsewhere !== null && $takenElsewhere($number)) {
                continue;
            }

            $inserted = DB::table('reserved_numbers')->insertOrIgnore([
                'number' => $number,
                'kind' => $kind,
                'created_at' => now(),
            ]);

            if ($inserted > 0) {
                return $number;
            }
        }

        throw new \RuntimeException('تعذّر حجز رقم فريد — نطاق الأرقام شبه ممتلئ.');
    }

    public static function sixDigits(int $min = 0): string
    {
        return str_pad((string) random_int($min, 999_999), 6, '0', STR_PAD_LEFT);
    }
}
