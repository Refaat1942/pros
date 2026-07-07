<?php

namespace App\Support\Barcode;

/**
 * مولّد باركود Code 128-B بصيغة SVG — أصلي بالكامل (بدون حزم خارجية).
 *
 * يدعم الأحرف الطباعية ASCII 32..126 (يكفي لأكواد مثل BC-RM-001).
 * يُستخدم لطباعة ملصقات الباركود الحراري في المخزن.
 */
final class Code128
{
    /** أنماط Code128 — كل قيمة سلسلة عرض وحدات (أسود/أبيض بالتناوب). */
    private const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312',
        '132212', '221213', '221312', '231212', '112232', '122132', '122231', '113222',
        '123122', '123221', '223211', '221132', '221231', '213212', '223112', '312131',
        '311222', '321122', '321221', '312212', '322112', '322211', '212123', '212321',
        '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121',
        '313121', '211331', '231131', '213113', '213311', '213131', '311123', '311321',
        '331121', '312113', '312311', '332111', '314111', '221411', '431111', '111224',
        '111422', '121124', '121421', '141122', '141221', '112214', '112412', '122114',
        '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112',
        '421211', '212141', '214121', '412121', '111143', '111341', '131141', '114113',
        '114311', '411113', '411311', '113141', '114131', '311141', '411131', '211412',
        '211214', '211232', '2331112',
    ];

    private const START_B = 104;

    private const STOP = 106;

    /**
     * يبني SVG للباركود.
     */
    public static function svg(string $data, int $height = 50, float $moduleWidth = 1.4, int $quietZone = 10): string
    {
        $modules = self::modules($data);

        $unitCount = 0;
        foreach ($modules as [$width]) {
            $unitCount += $width;
        }

        $totalUnits = $unitCount + ($quietZone * 2);
        $svgWidth = round($totalUnits * $moduleWidth, 2);

        $x = $quietZone * $moduleWidth;
        $rects = '';

        foreach ($modules as [$width, $isBar]) {
            $w = $width * $moduleWidth;
            if ($isBar) {
                $rects .= '<rect x="'.round($x, 2).'" y="0" width="'.round($w, 2)
                    .'" height="'.$height.'" fill="#000"/>';
            }
            $x += $w;
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$svgWidth.'" height="'.$height
            .'" viewBox="0 0 '.$svgWidth.' '.$height.'" preserveAspectRatio="none">'
            .'<rect width="100%" height="100%" fill="#fff"/>'.$rects.'</svg>';
    }

    /**
     * يحوّل النص إلى قائمة [عرض_الوحدة, هل_هو_شريط_أسود].
     *
     * @return list<array{0:int,1:bool}>
     */
    private static function modules(string $data): array
    {
        $codes = [self::START_B];
        $sum = self::START_B;
        $length = strlen($data);

        for ($i = 0; $i < $length; $i++) {
            $value = ord($data[$i]) - 32;
            if ($value < 0 || $value > 94) {
                $value = 0; // أي حرف غير مدعوم → مسافة
            }
            $codes[] = $value;
            $sum += $value * ($i + 1);
        }

        $codes[] = $sum % 103;   // checksum
        $codes[] = self::STOP;

        $modules = [];

        foreach ($codes as $code) {
            $pattern = self::PATTERNS[$code];
            $isBar = true; // كل نمط يبدأ بشريط أسود

            for ($j = 0, $plen = strlen($pattern); $j < $plen; $j++) {
                $modules[] = [(int) $pattern[$j], $isBar];
                $isBar = ! $isBar;
            }
        }

        return $modules;
    }
}
