<?php

namespace App\Support;

/**
 * استخراج حقول خطاب الموافقة من نص خام (عربي/إنجليزي).
 */
class OcrLetterParser
{
    /**
     * @param  array{
     *   patient_hint?: string|null,
     *   amount_hint?: float|null,
     *   company_hint?: string|null,
     * }  $hints
     * @return array{
     *   patient_name?: string,
     *   approved_amount?: float,
     *   company_name?: string,
     *   letter_ref?: string,
     *   letter_date?: string,
     * }
     */
    public static function parse(string $rawText, array $hints = []): array
    {
        $text = self::normalizeText($rawText);

        if ($text === '') {
            return [];
        }

        $result = [];

        $patient = self::extractPatientName($text, $hints['patient_hint'] ?? null);
        if ($patient) {
            $result['patient_name'] = $patient;
        }

        $amount = self::extractAmount($text, $hints['amount_hint'] ?? null);
        if ($amount !== null) {
            $result['approved_amount'] = $amount;
        }

        $company = self::extractCompany($text, $hints['company_hint'] ?? null);
        if ($company) {
            $result['company_name'] = $company;
        }

        $ref = self::extractLetterRef($text);
        if ($ref) {
            $result['letter_ref'] = $ref;
        }

        $date = self::extractLetterDate($text);
        if ($date) {
            $result['letter_date'] = $date;
        }

        return $result;
    }

    public static function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        // أرقام عربية-هندية → لاتينية
        $eastern = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $text = str_replace($eastern, $western, $text);

        return trim($text);
    }

    private static function extractPatientName(string $text, ?string $hint): ?string
    {
        $patterns = [
            '/اسم\s*المريض\s*[:\-\/]?\s*([^\n\r\d]{3,80})/u',
            '/المريض\s*[:\-\/]?\s*([^\n\r\d]{3,80})/u',
            '/بتوقيع\s*الكشف\s*الطبي\s*على\s*السيد\s*[\/:\-]?\s*([^\n\r\d]{3,80})/u',
            '/السيد\s*[\/:\-]?\s*([^\n\r\d]{3,80})/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $name = self::cleanLabelValue($m[1]);
                if (self::isPlausibleName($name)) {
                    return $name;
                }
            }
        }

        if ($hint && self::containsFuzzy($text, $hint)) {
            return trim($hint);
        }

        return null;
    }

    private static function extractAmount(string $text, ?float $hint): ?float
    {
        $candidates = [];

        if (preg_match_all('/(?:المبلغ|القيمة|يعتمد|إجمالي|مبلغ|amount)\s*[:\-]?\s*([\d][\d\s,\.]{2,15})/ui', $text, $matches)) {
            foreach ($matches[1] as $raw) {
                $val = self::parseNumber($raw);
                if ($val !== null && $val > 0) {
                    $candidates[] = $val;
                }
            }
        }

        if (preg_match_all('/([\d]{1,3}(?:[,\s][\d]{3})+(?:\.[\d]{2})?)\s*(?:جنيه|ج\.م|جنية|EGP)/ui', $text, $matches)) {
            foreach ($matches[1] as $raw) {
                $val = self::parseNumber($raw);
                if ($val !== null && $val > 0) {
                    $candidates[] = $val;
                }
            }
        }

        if (preg_match_all('/\b([\d]{4,9})(?:\.[\d]{2})?\b/u', $text, $matches)) {
            foreach ($matches[1] as $raw) {
                $val = self::parseNumber($raw);
                if ($val !== null && $val >= 100 && ! self::looksLikeYear($val)) {
                    $candidates[] = $val;
                }
            }
        }

        $candidates = array_values(array_unique($candidates));

        if ($hint !== null && $candidates) {
            foreach ($candidates as $candidate) {
                if (abs($candidate - $hint) < 0.01) {
                    return round($candidate, 2);
                }
            }
        }

        return $candidates ? round(max($candidates), 2) : null;
    }

    private static function extractCompany(string $text, ?string $hint): ?string
    {
        $patterns = [
            '/جهة\s*التعاقد\s*[:\-]?\s*([^\n\r]{3,80})/u',
            '/الجهة\s*الضامنة\s*[:\-]?\s*([^\n\r]{3,80})/u',
            '/السادة\s*[\/:\-]?\s*([^\n\r]{3,80})/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $company = self::cleanLabelValue($m[1]);
                if (mb_strlen($company) >= 3) {
                    return $company;
                }
            }
        }

        if ($hint && self::containsFuzzy($text, $hint)) {
            return trim($hint);
        }

        return null;
    }

    private static function extractLetterRef(string $text): ?string
    {
        $patterns = [
            '/خطاب\s*رقم\s*\(?\s*([0-9A-Za-z\-\/\.]{2,30})\s*\)?/u',
            '/رقم\s*الخطاب\s*[:\-]?\s*([0-9A-Za-z\-\/\.]{2,30})/u',
            '/إشارة\s*[:\-]?\s*([0-9A-Za-z\-\/\.]{2,30})/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                return trim($m[1]);
            }
        }

        return null;
    }

    private static function extractLetterDate(string $text): ?string
    {
        if (preg_match('/تاريخ\s*[:\-]?\s*(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{2,4})/u', $text, $m)) {
            return self::formatDateParts($m[1], $m[2], $m[3]);
        }

        if (preg_match('/(\d{1,2})[\/\-.](\d{1,2})[\/\-.](20\d{2})/u', $text, $m)) {
            return self::formatDateParts($m[1], $m[2], $m[3]);
        }

        return null;
    }

    private static function formatDateParts(string $d, string $m, string $y): string
    {
        $year = (int) $y;
        if ($year < 100) {
            $year += 2000;
        }

        return sprintf('%04d-%02d-%02d', $year, (int) $m, (int) $d);
    }

    private static function parseNumber(string $raw): ?float
    {
        $normalized = preg_replace('/[^\d.]/u', '', str_replace(',', '', $raw));

        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private static function cleanLabelValue(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/\s{2,}/u', ' ', $value) ?? $value;
        $value = preg_replace('/[:\-]+$/u', '', $value) ?? $value;

        return trim($value);
    }

    private static function isPlausibleName(string $name): bool
    {
        $name = trim($name);

        if (mb_strlen($name) < 3) {
            return false;
        }

        return (bool) preg_match('/\p{Arabic}/u', $name);
    }

    private static function containsFuzzy(string $haystack, string $needle): bool
    {
        $norm = static fn (string $s) => preg_replace('/\s+/u', '', mb_strtolower(trim($s)));

        return str_contains($norm($haystack), $norm($needle));
    }

    private static function looksLikeYear(float $value): bool
    {
        $int = (int) $value;

        return $int >= 1900 && $int <= 2100;
    }
}
