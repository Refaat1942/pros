<?php

namespace App\Support;

use App\Enums\PaymentMethod;
use App\Models\Payment;

/**
 * يجهّز بيانات إيصال الدفع للطباعة — رقم الإيصال (السيريال)، المبلغ رقماً وكتابةً،
 * وسيلة الدفع ومرجعها، والجهة/المريض.
 */
class PaymentReceiptPresenter
{
    /**
     * @return array{
     *   payment_no: string,
     *   case_no: ?string,
     *   order_ref: ?string,
     *   patient_serial: ?string,
     *   patient_name: string,
     *   entity: string,
     *   amount: float,
     *   amount_words: string,
     *   method_label: string,
     *   reference: ?string,
     *   reference_label: string,
     *   received_by: ?string,
     *   received_at: ?string,
     *   notes: ?string
     * }
     */
    public static function fromPayment(Payment $payment): array
    {
        $payment->loadMissing(['caseRecord', 'patient']);

        $case = $payment->caseRecord;
        $method = PaymentMethod::tryFrom((string) $payment->method);
        $amount = round((float) $payment->amount, 2);

        return [
            'payment_no' => $payment->payment_no,
            'case_no' => $case?->case_no,
            'order_ref' => $case?->order_ref,
            'patient_serial' => $payment->patient?->patient_serial,
            'patient_name' => $payment->patient_name ?: ($payment->patient?->name ?? '—'),
            'entity' => $case?->displayEntity() ?? '—',
            'amount' => $amount,
            'amount_words' => self::amountInWords($amount),
            'method_label' => $method?->label() ?? ($payment->method ?: '—'),
            'reference' => $payment->reference,
            'reference_label' => $method?->referenceLabel() ?? 'رقم العملية',
            'received_by' => $payment->received_by,
            'received_at' => ClinicTime::format($payment->received_at, 'd/m/Y H:i'),
            'notes' => $payment->notes,
        ];
    }

    /**
     * تفقيط المبلغ بالعربية — جنيهات وقروش (حتى مئات الملايين).
     */
    public static function amountInWords(float $amount): string
    {
        $pounds = (int) floor($amount);
        $piasters = (int) round(($amount - $pounds) * 100);

        $text = self::integerToArabic($pounds).' جنيهاً مصرياً';

        if ($piasters > 0) {
            $text .= ' و'.self::integerToArabic($piasters).' قرشاً';
        }

        return $text.' فقط لا غير.';
    }

    private static function integerToArabic(int $number): string
    {
        if ($number === 0) {
            return 'صفر';
        }

        $scales = ['', ' ألف', ' مليون', ' مليار'];
        $groups = [];

        while ($number > 0) {
            $groups[] = $number % 1000;
            $number = intdiv($number, 1000);
        }

        $parts = [];
        for ($i = count($groups) - 1; $i >= 0; $i--) {
            $g = $groups[$i];
            if ($g === 0) {
                continue;
            }

            $words = self::threeDigitsToArabic($g);

            // صيغ خاصة للألف والمليون (ألفان/ألفين، مليونان...).
            if ($i === 1) {
                $words = match (true) {
                    $g === 1 => 'ألف',
                    $g === 2 => 'ألفان',
                    $g <= 10 => $words.' آلاف',
                    default => $words.' ألف',
                };
            } elseif ($i >= 2) {
                $scale = trim($scales[$i]);
                $words = $g === 1 ? $scale : ($g === 2 ? $scale.'ان' : $words.' '.$scale);
            }

            $parts[] = $words;
        }

        return implode(' و', $parts);
    }

    private static function threeDigitsToArabic(int $n): string
    {
        $ones = ['', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة',
            'عشرة', 'أحد عشر', 'اثنا عشر', 'ثلاثة عشر', 'أربعة عشر', 'خمسة عشر',
            'ستة عشر', 'سبعة عشر', 'ثمانية عشر', 'تسعة عشر'];
        $tens = ['', '', 'عشرون', 'ثلاثون', 'أربعون', 'خمسون', 'ستون', 'سبعون', 'ثمانون', 'تسعون'];
        $hundreds = ['', 'مائة', 'مئتان', 'ثلاثمائة', 'أربعمائة', 'خمسمائة', 'ستمائة', 'سبعمائة', 'ثمانمائة', 'تسعمائة'];

        $parts = [];

        $h = intdiv($n, 100);
        $rem = $n % 100;

        if ($h > 0) {
            $parts[] = $hundreds[$h];
        }

        if ($rem > 0) {
            if ($rem < 20) {
                $parts[] = $ones[$rem];
            } else {
                $t = intdiv($rem, 10);
                $o = $rem % 10;
                $parts[] = $o > 0 ? $ones[$o].' و'.$tens[$t] : $tens[$t];
            }
        }

        return implode(' و', $parts);
    }
}
